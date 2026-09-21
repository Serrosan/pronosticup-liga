<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\EstadioRequest;
use App\Http\Resources\EstadioResource;
use App\Models\Estadio;

class EstadioAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return EstadioResource::collection(Estadio::orderBy('nombre')->get());
    }

    public function show(Estadio $estadio)
    {
        return $this->mostrarRecurso($estadio);
    }

    public function store(EstadioRequest $request)
    {
        return $this->crearRecurso($request, Estadio::class, EstadioResource::class);
    }

    public function update(EstadioRequest $request, Estadio $estadio)
    {
        return $this->actualizarRecurso($request, $estadio, EstadioResource::class);
    }

    public function destroy(Estadio $estadio)
    {
        return $this->eliminarRecurso($estadio, 'Estadio');
    }
}