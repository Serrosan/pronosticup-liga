<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Services\CambiosPlantillaService;
use App\Services\ComparadorPlantillasService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Admin → Comparar plantillas. Consultar y mirar solo leen; cada acción cambia
 * un jugador concreto y solo cuando se pulsa. Los errores esperables vuelven
 * como 422 con un mensaje que se entiende.
 */
class ComparadorPlantillasAdminController extends Controller
{
    public function __construct(private ComparadorPlantillasService $comparador) {}

    public function index()
    {
        return response()->json(['data' => $this->comparador->resultado()]);
    }

    public function consultar(Equipo $equipo)
    {
        return $this->intentar(fn () => ['data' => $this->comparador->consultarEquipo($equipo)]);
    }

    public function vincular(Request $request, Jugador $jugador)
    {
        $datos = $request->validate(['id_api' => ['required', 'integer', 'min:1']]);

        return $this->intentar(fn () => ['message' => $this->comparador->vincular($jugador, (int) $datos['id_api'])]);
    }

    public function confirmarIdentificador(Jugador $jugador)
    {
        return $this->intentar(fn () => ['message' => $this->comparador->confirmarIdentificador($jugador)]);
    }

    public function sigue(Jugador $jugador)
    {
        return $this->intentar(fn () => ['message' => $this->comparador->marcarQueSigue($jugador)]);
    }

    public function quitarIdentificador(Jugador $jugador)
    {
        return $this->intentar(fn () => ['message' => $this->comparador->quitarIdentificador($jugador)]);
    }

    public function alta(Request $request)
    {
        $datos = $request->validate([
            'id_api' => ['required', 'integer', 'min:1'],
            'id_equipo' => ['required', 'integer', 'exists:equipos,id'],
            'posicion' => ['required', Rule::in(CambiosPlantillaService::POSICIONES)],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
            'fecha' => ['required', 'date'],
        ]);

        return $this->intentar(fn () => ['message' => $this->comparador->darDeAlta(
            (int) $datos['id_api'], (int) $datos['id_equipo'], $datos['posicion'],
            isset($datos['dorsal']) ? (int) $datos['dorsal'] : null, $datos['fecha'],
        )]);
    }

    public function traspaso(Request $request, Jugador $jugador)
    {
        $datos = $request->validate([
            'id_equipo_destino' => ['required', 'integer', 'exists:equipos,id'],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
            'fecha' => ['required', 'date'],
        ]);

        return $this->intentar(fn () => ['message' => $this->comparador->traspasar(
            $jugador, (int) $datos['id_equipo_destino'], isset($datos['dorsal']) ? (int) $datos['dorsal'] : null, $datos['fecha'],
        )]);
    }

    public function salida(Request $request, Jugador $jugador)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'dar_de_baja' => ['required', 'boolean'],
        ]);

        return $this->intentar(fn () => ['message' => $this->comparador->salida($jugador, $datos['fecha'], (bool) $datos['dar_de_baja'])]);
    }

    private function intentar(callable $accion)
    {
        try {
            return response()->json($accion());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
