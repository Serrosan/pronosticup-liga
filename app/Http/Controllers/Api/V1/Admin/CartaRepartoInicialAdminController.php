<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\RarezaProbabilidadLiga;
use App\Services\SorteoCartasService;
use Illuminate\Http\Request;

class CartaRepartoInicialAdminController extends Controller
{
    public function __construct(private SorteoCartasService $sorteo) {}

    /**
     * Reparto manual único, pensado para el momento en que se activa Cartas en una liga
     * a mitad de temporada — da a CADA miembro la misma CANTIDAD de cartas (la que
     * elija el admin), pero cada carta sorteada de forma independiente (categoría al
     * azar entre las activas + rareza según la configuración de esa liga), así que el
     * contenido real no tiene por qué coincidir entre 2 personas aunque reciban el mismo
     * número de cartas.
     */
    public function repartir(Request $request, Liga $liga)
    {
        $validated = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['message' => 'Esta liga no tiene el modo Cartas activado.'], 422);
        }

        $categorias = CategoriaCarta::where('activa', true)->get();

        if ($categorias->isEmpty()) {
            return response()->json(['message' => 'No hay ninguna categoría de carta activa todavía.'], 422);
        }

        $rarezasPorCategoria = RarezaProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('id_categoria');
        $miembros = $liga->usuarios()->get(['users.id']);

        $jornadaActual = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereIn('estado', ['Programado', 'Aplazado', 'En juego'])
            ->orderBy('jornada')
            ->value('jornada') ?? 1;

        $creadas = 0;

        foreach ($miembros as $miembro) {
            for ($i = 0; $i < $validated['cantidad']; $i++) {
                $categoriaAlAzar = $categorias->random();

                $filas = $rarezasPorCategoria->get($categoriaAlAzar->id) ?? collect();
                $porcentajes = $filas->isNotEmpty()
                    ? $filas->pluck('porcentaje', 'rareza')->toArray()
                    : ['Comun' => 65, 'PocoComun' => 25, 'Rara' => 8, 'Legendaria' => 2];

                $tipoElegido = $this->sorteo->elegirCartaAlAzar($categoriaAlAzar->id, $porcentajes);

                if ($tipoElegido) {
                    CartaUsuario::create([
                        'id_usuario' => $miembro->id,
                        'id_liga' => $liga->id,
                        'id_tipo_carta' => $tipoElegido->id,
                        'jornada_obtenida' => $jornadaActual,
                        'obtenida_en' => now(),
                        'origen' => 'manual',
                        'estado' => 'en_mano',
                    ]);
                    $creadas++;
                }
            }
        }

        return response()->json([
            'message' => "Reparto inicial completado: {$creadas} carta(s) repartidas entre {$miembros->count()} miembro(s) ({$validated['cantidad']} cada uno).",
        ]);
    }
}