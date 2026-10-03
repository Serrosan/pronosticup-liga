<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;

class EquipoAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return EquipoResource::collection(Equipo::orderBy('nombre')->get());
    }

    public function show(Equipo $equipo)
    {
        return $this->mostrarRecurso($equipo);
    }

    public function store(EquipoRequest $request)
    {
        return $this->crearRecurso($request, Equipo::class, EquipoResource::class);
    }

    public function update(EquipoRequest $request, Equipo $equipo)
    {
        return $this->actualizarRecurso($request, $equipo, EquipoResource::class);
    }

    public function destroy(Equipo $equipo)
    {
        return $this->eliminarRecurso($equipo, 'Equipo');
    }
}