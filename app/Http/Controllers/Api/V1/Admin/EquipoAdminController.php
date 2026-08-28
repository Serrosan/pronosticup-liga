<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;

class EquipoAdminController extends Controller
{
    public function index()
    {
        return EquipoResource::collection(Equipo::orderBy('nombre')->get());
    }

    public function show(Equipo $equipo)
    {
        return response()->json(['data' => $equipo]);
    }

    public function store(EquipoRequest $request)
    {
        $equipo = Equipo::create($request->validated());

        return new EquipoResource($equipo);
    }

    public function update(EquipoRequest $request, Equipo $equipo)
    {
        $equipo->update($request->validated());

        return new EquipoResource($equipo);
    }

    public function destroy(Equipo $equipo)
    {
        $equipo->delete();

        return response()->json(['message' => 'Equipo eliminado.']);
    }
}