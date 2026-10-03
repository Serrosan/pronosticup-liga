<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\JugadorRequest;
use App\Http\Resources\JugadorResource;
use App\Models\EventoPartido;
use App\Models\Jugador;

class JugadorAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return JugadorResource::collection(Jugador::orderBy('nombre')->get());
    }

    public function show(Jugador $jugador)
    {
        return $this->mostrarRecurso($jugador);
    }

    public function store(JugadorRequest $request)
    {
        return $this->crearRecurso($request, Jugador::class, JugadorResource::class);
    }

    public function update(JugadorRequest $request, Jugador $jugador)
    {
        return $this->actualizarRecurso($request, $jugador, JugadorResource::class);
    }

    // destroy() se queda fuera del trait a propósito: necesita comprobar
    // eventos de partido asociados antes de borrar, con flujo de confirmación.
    public function destroy(Jugador $jugador)
    {
        $totalEventos = EventoPartido::where('id_jugador', $jugador->id)
            ->orWhere('id_jugador_relacionado', $jugador->id)
            ->count();

        if ($totalEventos > 0 && ! request()->boolean('confirmado')) {
            return response()->json([
                'message' => "Este jugador tiene {$totalEventos} eventos de partido asociados (goles, tarjetas, sustituciones).",
                'requiere_confirmacion' => true,
                'total_eventos' => $totalEventos,
            ], 409);
        }

        $jugador->delete();

        return response()->json(['message' => 'Jugador eliminado.']);
    }
}