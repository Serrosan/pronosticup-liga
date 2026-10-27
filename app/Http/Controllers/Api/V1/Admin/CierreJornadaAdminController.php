<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\CartaRepartoController;
use App\Http\Controllers\Api\V1\JornadaController;
use App\Http\Controllers\Controller;
use App\Models\Liga;
use App\Services\CierreJornadaService;
use App\Services\TareasAdmin;
use Illuminate\Http\Request;

/**
 * /admin/cierre-jornada — la lista de cierre: qué está hecho y qué falta en
 * cada liga, y los botones para hacerlo sin cambiar de liga activa.
 *
 * Los botones NO tienen lógica propia: llaman a los mismos métodos de siempre
 * (JornadaController y CartaRepartoController), que trabajan sobre "la liga
 * activa" de quien los llama. Aquí solo se les dice, para esa llamada, cuál
 * es la liga — sin tocar la liga activa guardada del usuario.
 */
class CierreJornadaAdminController extends Controller
{
    public function __construct(private CierreJornadaService $cierre) {}

    public function index(Request $request)
    {
        $sugerida = $this->cierre->jornadaSugerida();
        $jornada = (int) $request->query('jornada', $sugerida);
        $jornada = max(1, min(TareasAdmin::TOTAL_JORNADAS, $jornada));

        return response()->json(['data' => array_merge(
            $this->cierre->estado($jornada, $request->user()),
            [
                'jornada_sugerida' => $sugerida,
                'total_jornadas' => TareasAdmin::TOTAL_JORNADAS,
                'sin_cerrar' => $this->cierre->jornadasSinCerrar(),
            ]
        )]);
    }

    public function cerrar(Request $request, Liga $liga, int $jornada)
    {
        return $this->enLiga($request, $liga, fn () => app(JornadaController::class)->cerrar($request, $jornada));
    }

    public function goleadores(Request $request, Liga $liga, int $jornada)
    {
        return $this->enLiga($request, $liga, fn () => app(JornadaController::class)->recalcularEventos($request, $jornada));
    }

    public function repartir(Request $request, Liga $liga, int $jornada)
    {
        return $this->enLiga($request, $liga, fn () => app(CartaRepartoController::class)->repartirJornada($request, $jornada));
    }

    /**
     * "Cerrar todo": los pasos que quedan, seguidos y con las MISMAS acciones de
     * los botones sueltos. Antes se vuelve a comprobar que todo está en verde.
     * Si un paso falla se para ahí: lo hecho queda hecho (cada paso guarda lo
     * suyo) y el resto se termina con su botón, como siempre.
     */
    public function todo(Request $request, Liga $liga, int $jornada)
    {
        $estado = $this->cierre->estado($jornada, $request->user());
        $deLiga = collect($estado['ligas'])->firstWhere('id', $liga->id);

        if (! $deLiga) {
            return response()->json(['message' => 'Esa liga no es de la temporada actual.'], 422);
        }
        if (! $deLiga['un_paso']['posible']) {
            return response()->json(['message' => 'No se puede cerrar todo de una vez: '.$deLiga['un_paso']['motivo']], 422);
        }

        $hechos = [];
        foreach ($deLiga['un_paso']['pendientes'] as $paso) {
            try {
                $respuesta = $this->{$paso['clave']}($request, $liga, $jornada);
                $codigo = $respuesta instanceof \Symfony\Component\HttpFoundation\Response ? $respuesta->getStatusCode() : 200;
                $mensaje = $respuesta instanceof \Illuminate\Http\JsonResponse ? ($respuesta->getData(true)['message'] ?? null) : null;
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                [$codigo, $mensaje] = [$e->getStatusCode(), $e->getMessage()];
            } catch (\Illuminate\Validation\ValidationException $e) {
                [$codigo, $mensaje] = [422, $e->getMessage()];
            } catch (\Throwable $e) {
                report($e);
                [$codigo, $mensaje] = [500, 'error inesperado (está en el registro de errores)'];
            }

            if ($codigo >= 400) {
                $antes = $hechos ? ' Hecho antes: '.implode(', ', $hechos).'.' : ' No se ha hecho nada.';

                return response()->json([
                    'message' => "Se ha parado en «{$paso['titulo']}»: ".($mensaje ?: 'no se pudo hacer.').$antes.' Sigue paso a paso.',
                ], $codigo >= 500 ? 500 : 422);
            }

            $hechos[] = mb_strtolower($paso['titulo']);
        }

        return response()->json(['message' => "{$liga->nombre}, jornada {$jornada}: hecho todo (".implode(', ', $hechos).').']);
    }

    /**
     * Ejecuta la acción haciendo que, solo durante esta llamada, "la liga activa"
     * del usuario sea la indicada. No se guarda nada en su perfil. Las propias
     * acciones siguen exigiendo que sea admin de esa liga.
     */
    private function enLiga(Request $request, Liga $liga, callable $accion)
    {
        $usuario = $request->user();
        $usuario->setRelation('ligaActiva', $liga);

        try {
            return $accion();
        } finally {
            $usuario->unsetRelation('ligaActiva');
        }
    }
}
