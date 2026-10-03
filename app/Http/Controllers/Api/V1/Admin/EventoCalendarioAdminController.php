<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventoCalendarioRequest;
use App\Models\EventoCalendario;

class EventoCalendarioAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return response()->json(['data' => EventoCalendario::orderBy('fecha_inicio')->get()]);
    }

    public function show(EventoCalendario $eventoCalendario)
    {
        return $this->mostrarRecurso($eventoCalendario);
    }

    public function store(EventoCalendarioRequest $request)
    {
        return $this->crearRecursoPlano($request, EventoCalendario::class);
    }

    public function update(EventoCalendarioRequest $request, EventoCalendario $eventoCalendario)
    {
        return $this->actualizarRecursoPlano($request, $eventoCalendario);
    }

    public function destroy(EventoCalendario $eventoCalendario)
    {
        return $this->eliminarRecurso($eventoCalendario, 'Evento');
    }
}