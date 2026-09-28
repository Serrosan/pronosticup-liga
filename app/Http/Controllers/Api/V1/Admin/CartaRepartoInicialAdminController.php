<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\RarezaProbabilidadLiga;
use App\Models\User;
use App\Notifications\CartasRepartidas;
use App\Services\ProbabilidadesPorDefecto;
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

        $jornadaActual = CalendarioPartido::jornadaActualParaTemporada($liga->id_temporada);

        $creadas = 0;
        $recibidas = [];

        foreach ($miembros as $miembro) {
            for ($i = 0; $i < $validated['cantidad']; $i++) {
                $categoriaAlAzar = $categorias->random();

                $filas = $rarezasPorCategoria->get($categoriaAlAzar->id) ?? collect();
                $porcentajes = $filas->isNotEmpty()
                    ? $filas->pluck('porcentaje', 'rareza')->toArray()
                    : ProbabilidadesPorDefecto::RAREZA;

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
                    $recibidas[$miembro->id] = ($recibidas[$miembro->id] ?? 0) + 1;
                }
            }
        }

        $this->avisarReparto($liga, $recibidas);

        return response()->json([
            'message' => "Reparto inicial completado: {$creadas} carta(s) repartidas entre {$miembros->count()} miembro(s) ({$validated['cantidad']} cada uno).",
        ]);
    }

    /**
     * Avisa por la campana a cada miembro de lo que ha recibido. Un fallo al avisar
     * nunca debe hacer que el admin repita un reparto que ya está hecho.
     */
    private function avisarReparto(Liga $liga, array $recibidas): void
    {
        if (empty($recibidas)) {
            return;
        }

        $topeMano = $liga->tope_mano_cartas ?? 8;

        User::whereIn('id', array_keys($recibidas))->get()->each(function (User $usuario) use ($liga, $recibidas, $topeMano) {
            try {
                $enMano = CartaUsuario::where('id_liga', $liga->id)
                    ->where('id_usuario', $usuario->id)
                    ->where('estado', 'en_mano')
                    ->count();

                $usuario->notify(new CartasRepartidas(
                    cantidad: $recibidas[$usuario->id],
                    sobreElTope: $enMano > $topeMano,
                    inicial: true,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}