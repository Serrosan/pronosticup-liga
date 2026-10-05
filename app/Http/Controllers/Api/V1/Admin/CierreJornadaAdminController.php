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
