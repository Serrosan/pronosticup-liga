<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusTop3ProbabilidadLiga;
use App\Models\CategoriaCarta;
use App\Models\ConfiguracionCartasLiga;
use App\Models\Liga;
use App\Models\RarezaProbabilidadLiga;
use App\Services\ProbabilidadesPorDefecto;
use Illuminate\Http\Request;

class ConfiguracionCartasLigaAdminController extends Controller
{
    public function mostrar(Liga $liga)
    {
        $categorias = CategoriaCarta::where('activa', true)->orderBy('nombre')->get();
        $configuraciones = ConfiguracionCartasLiga::where('id_liga', $liga->id)->get()->keyBy('id_categoria');
        $rarezasPorCategoria = RarezaProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('id_categoria');

        $datos = $categorias->map(function ($categoria) use ($configuraciones, $rarezasPorCategoria) {
            $config = $configuraciones->get($categoria->id);
            $rarezasCategoria = ($rarezasPorCategoria->get($categoria->id) ?? collect())->keyBy('rareza');

            return [
                'id_categoria' => $categoria->id,
                'nombre' => $categoria->nombre,
                'cantidad_reparto_semanal' => $config->cantidad_reparto_semanal ?? 1,
                'rarezas' => [
                    'Comun' => $rarezasCategoria->get('Comun')->porcentaje ?? ProbabilidadesPorDefecto::RAREZA['Comun'],
                    'PocoComun' => $rarezasCategoria->get('PocoComun')->porcentaje ?? ProbabilidadesPorDefecto::RAREZA['PocoComun'],
                    'Rara' => $rarezasCategoria->get('Rara')->porcentaje ?? ProbabilidadesPorDefecto::RAREZA['Rara'],
                    'Legendaria' => $rarezasCategoria->get('Legendaria')->porcentaje ?? ProbabilidadesPorDefecto::RAREZA['Legendaria'],
                ],
            ];
        });

        $bonusTop3Guardado = BonusTop3ProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('posicion');

        $bonusTop3 = collect([1, 2, 3])->map(function ($posicion) use ($bonusTop3Guardado) {
            $filasPosicion = ($bonusTop3Guardado->get($posicion) ?? collect())->keyBy('rareza');
            $defecto = ProbabilidadesPorDefecto::TOP3[$posicion];

            return [
                'posicion' => $posicion,
                'rarezas' => [
                    'Comun' => $filasPosicion->get('Comun')->porcentaje ?? $defecto['Comun'],
                    'PocoComun' => $filasPosicion->get('PocoComun')->porcentaje ?? $defecto['PocoComun'],
                    'Rara' => $filasPosicion->get('Rara')->porcentaje ?? $defecto['Rara'],
                    'Legendaria' => $filasPosicion->get('Legendaria')->porcentaje ?? $defecto['Legendaria'],
                ],
            ];
        });

        return response()->json([
            'data' => [
                'liga' => [
                    'id' => $liga->id,
                    'nombre' => $liga->nombre,
                    'tope_mano_cartas' => $liga->tope_mano_cartas ?? 8,
                ],
                'categorias' => $datos,
                'bonus_top3' => $bonusTop3,
            ],
        ]);
    }

    public function actualizar(Request $request, Liga $liga)
    {
        $validated = $request->validate([
            'tope_mano_cartas' => ['required', 'integer', 'min:1', 'max:50'],
            'categorias' => ['required', 'array', 'min:1'],
            'categorias.*.id_categoria' => ['required', 'exists:categorias_carta,id'],
            'categorias.*.cantidad_reparto_semanal' => ['required', 'integer', 'min:0', 'max:10'],
            'categorias.*.rarezas.Comun' => ['required', 'integer', 'min:0', 'max:100'],
            'categorias.*.rarezas.PocoComun' => ['required', 'integer', 'min:0', 'max:100'],
            'categorias.*.rarezas.Rara' => ['required', 'integer', 'min:0', 'max:100'],
            'categorias.*.rarezas.Legendaria' => ['required', 'integer', 'min:0', 'max:100'],
            'bonus_top3' => ['required', 'array', 'size:3'],
            'bonus_top3.*.posicion' => ['required', 'integer', 'in:1,2,3'],
            'bonus_top3.*.rarezas.Comun' => ['required', 'integer', 'min:0', 'max:100'],
            'bonus_top3.*.rarezas.PocoComun' => ['required', 'integer', 'min:0', 'max:100'],
            'bonus_top3.*.rarezas.Rara' => ['required', 'integer', 'min:0', 'max:100'],
            'bonus_top3.*.rarezas.Legendaria' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        foreach ($validated['categorias'] as $cat) {
            $suma = $cat['rarezas']['Comun'] + $cat['rarezas']['PocoComun'] + $cat['rarezas']['Rara'] + $cat['rarezas']['Legendaria'];

            if ($suma !== 100) {
                return response()->json([
                    'message' => "Los porcentajes de rareza de una categoría suman {$suma}, deben sumar exactamente 100.",
                ], 422);
            }
        }

        foreach ($validated['bonus_top3'] as $fila) {
            $suma = $fila['rarezas']['Comun'] + $fila['rarezas']['PocoComun'] + $fila['rarezas']['Rara'] + $fila['rarezas']['Legendaria'];

            if ($suma !== 100) {
                return response()->json([
                    'message' => "Los porcentajes del bonus Top 3 (posición {$fila['posicion']}) suman {$suma}, deben sumar exactamente 100.",
                ], 422);
            }
        }

        $liga->update(['tope_mano_cartas' => $validated['tope_mano_cartas']]);

        foreach ($validated['categorias'] as $cat) {
            ConfiguracionCartasLiga::updateOrCreate(
                ['id_liga' => $liga->id, 'id_categoria' => $cat['id_categoria']],
                ['cantidad_reparto_semanal' => $cat['cantidad_reparto_semanal']]
            );

            foreach ($cat['rarezas'] as $rareza => $porcentaje) {
                RarezaProbabilidadLiga::updateOrCreate(
                    ['id_liga' => $liga->id, 'id_categoria' => $cat['id_categoria'], 'rareza' => $rareza],
                    ['porcentaje' => $porcentaje]
                );
            }
        }

        foreach ($validated['bonus_top3'] as $fila) {
            foreach ($fila['rarezas'] as $rareza => $porcentaje) {
                BonusTop3ProbabilidadLiga::updateOrCreate(
                    ['id_liga' => $liga->id, 'posicion' => $fila['posicion'], 'rareza' => $rareza],
                    ['porcentaje' => $porcentaje]
                );
            }
        }

        return response()->json(['message' => 'Configuración de Cartas actualizada correctamente.']);
    }
}