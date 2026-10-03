<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\EntrenadorRequest;
use App\Http\Resources\EntrenadorResource;
use App\Models\Entrenador;

class EntrenadorAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return EntrenadorResource::collection(Entrenador::with('equipoActual')->orderBy('nombre')->get());
    }

    public function show(Entrenador $entrenador)
    {
        return $this->mostrarRecurso($entrenador);
    }

    public function store(EntrenadorRequest $request)
    {
        return $this->crearRecurso($request, Entrenador::class, EntrenadorResource::class);
    }

    public function update(EntrenadorRequest $request, Entrenador $entrenador)
    {
        return $this->actualizarRecurso($request, $entrenador, EntrenadorResource::class);
    }

    public function destroy(Entrenador $entrenador)
    {
        return $this->eliminarRecurso($entrenador, 'Entrenador');
    }
}