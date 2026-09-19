<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BonusTop3ProbabilidadLiga;
use App\Models\CategoriaCarta;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\ConfiguracionCartasLiga;
use App\Models\EventoPuntos;
use App\Models\RarezaProbabilidadLiga;
use App\Services\SorteoCartasService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartaRepartoController extends Controller
{
    private const DEFECTOS_TOP3 = [
        1 => ['Comun' => 35, 'PocoComun' => 35, 'Rara' => 22, 'Legendaria' => 8],
        2 => ['Comun' => 45, 'PocoComun' => 33, 'Rara' => 17, 'Legendaria' => 5],
        3 => ['Comun' => 55, 'PocoComun' => 30, 'Rara' => 12, 'Legendaria' => 3],
    ];

    public function __construct(private SorteoCartasService $sorteo) {}

    public function repartirJornada(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esAdmin = $liga->usuarios()
            ->where('id_usuario', $request->user()->id)
            ->wherePivot('rol', 'Admin')
            ->exists();

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo el admin de la liga puede repartir cartas.'], 403);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['message' => 'Esta liga no tiene el modo Cartas activado.'], 422);
        }

        $yaCerrada = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();
        if (! $yaCerrada) {
            return response()->json(['message' => 'Cierra la jornada primero.'], 422);
        }

        $yaRepartida = CartaUsuario::where('id_liga', $liga->id)->where('jornada_obtenida', $jornada)->exists();
        if ($yaRepartida) {
            return response()->json(['message' => 'Las cartas de esta jornada ya se repartieron antes. Si necesitas corregir algo, usa el panel de admin para dar/retirar cartas sueltas.'], 409);
        }

        $topeMano = $liga->tope_mano_cartas ?? 8;
        $categorias = CategoriaCarta::where('activa', true)->get();

        if ($categorias->isEmpty()) {
            return response()->json(['message' => 'No hay ninguna categoría de carta activa todavía.'], 422);
        }

        $configuraciones = ConfiguracionCartasLiga::where('id_liga', $liga->id)->get()->keyBy('id_categoria');
        $rarezasPorCategoria = RarezaProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('id_categoria');
        $miembros = $liga->usuarios()->get(['users.id']);

        $cartasBaseCreadas = 0;
        $idsUsuariosSobreElTope = [];

        foreach ($miembros as $miembro) {
            foreach ($categorias as $categoria) {
                $cantidad = $configuraciones->get($categoria->id)->cantidad_reparto_semanal ?? 1;
                $porcentajes = $this->porcentajesDeCategoria($rarezasPorCategoria, $categoria->id);

                for ($i = 0; $i < $cantidad; $i++) {
                    $tipoElegido = $this->sorteo->elegirCartaAlAzar($categoria->id, $porcentajes);

                    if ($tipoElegido) {
                        CartaUsuario::create([
                            'id_usuario' => $miembro->id,
                            'id_liga' => $liga->id,
                            'id_tipo_carta' => $tipoElegido->id,
                            'jornada_obtenida' => $jornada,
                            'obtenida_en' => now(),
                            'origen' => 'reparto_semanal',
                            'estado' => 'en_mano',
                        ]);
                        $cartasBaseCreadas++;
                    }
                }
            }

            $manoFinal = CartaUsuario::where('id_liga', $liga->id)->where('id_usuario', $miembro->id)->where('estado', 'en_mano')->count();
            if ($manoFinal > $topeMano) {
                $idsUsuariosSobreElTope[] = $miembro->id;
            }
        }

        // --- Bonus del Top 3 de esta jornada concreta ---
        $top3 = EventoPuntos::where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->selectRaw('id_usuario, SUM(puntos) as puntos')
            ->groupBy('id_usuario')
            ->orderByDesc('puntos')
            ->limit(3)
            ->get()
            ->values();

        $bonusPorcentajesGuardados = BonusTop3ProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('posicion');
        $cartasBonusCreadas = 0;

        foreach ($top3 as $indice => $fila) {
            if ($fila->puntos <= 0) {
                continue;
            }

            $posicion = $indice + 1;

            $filasPosicion = $bonusPorcentajesGuardados->get($posicion) ?? collect();
            $porcentajes = $filasPosicion->isNotEmpty()
                ? $filasPosicion->pluck('porcentaje', 'rareza')->toArray()
                : self::DEFECTOS_TOP3[$posicion];

            $categoriaAlAzar = $categorias->random();
            $tipoElegido = $this->sorteo->elegirCartaAlAzar($categoriaAlAzar->id, $porcentajes);

            if ($tipoElegido) {
                CartaUsuario::create([
                    'id_usuario' => $fila->id_usuario,
                    'id_liga' => $liga->id,
                    'id_tipo_carta' => $tipoElegido->id,
                    'jornada_obtenida' => $jornada,
                    'obtenida_en' => now(),
                    'origen' => 'bonus_top3',
                    'estado' => 'en_mano',
                ]);
                $cartasBonusCreadas++;

                $manoFinal = CartaUsuario::where('id_liga', $liga->id)->where('id_usuario', $fila->id_usuario)->where('estado', 'en_mano')->count();
                if ($manoFinal > $topeMano && ! in_array($fila->id_usuario, $idsUsuariosSobreElTope)) {
                    $idsUsuariosSobreElTope[] = $fila->id_usuario;
                }
            }
        }

        $mensajeAviso = count($idsUsuariosSobreElTope) > 0
            ? ' ⚠️ '.count($idsUsuariosSobreElTope).' usuario(s) han superado el tope de su mano — tendrán que descartar alguna carta ellos mismos cuando esté disponible esa opción.'
            : '';

        return response()->json([
            'message' => "Reparto completado: {$cartasBaseCreadas} carta(s) base + {$cartasBonusCreadas} carta(s) de bonus Top 3.{$mensajeAviso}",
        ]);
    }

    private function porcentajesDeCategoria(Collection $rarezasPorCategoria, int $idCategoria): array
    {
        $filas = $rarezasPorCategoria->get($idCategoria) ?? collect();

        if ($filas->isEmpty()) {
            return ['Comun' => 65, 'PocoComun' => 25, 'Rara' => 8, 'Legendaria' => 2];
        }

        return $filas->pluck('porcentaje', 'rareza')->toArray();
    }
}