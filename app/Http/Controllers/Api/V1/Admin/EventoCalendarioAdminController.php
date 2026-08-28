<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventoCalendarioRequest;
use App\Models\EventoCalendario;

class EventoCalendarioAdminController extends Controller
{
    public function index()
    {
        return response()->json(['data' => EventoCalendario::orderBy('fecha_inicio')->get()]);
    }

    public function show(EventoCalendario $eventoCalendario)
    {
        return response()->json(['data' => $eventoCalendario]);
    }

    public function store(EventoCalendarioRequest $request)
    {
        $evento = EventoCalendario::create($request->validated());

        return response()->json(['data' => $evento]);
    }

    public function update(EventoCalendarioRequest $request, EventoCalendario $eventoCalendario)
    {
        $eventoCalendario->update($request->validated());

        return response()->json(['data' => $eventoCalendario]);
    }

    public function destroy(EventoCalendario $eventoCalendario)
    {
        $eventoCalendario->delete();

        return response()->json(['message' => 'Evento eliminado.']);
    }
}