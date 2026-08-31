<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntrenadorRequest;
use App\Http\Resources\EntrenadorResource;
use App\Models\Entrenador;

class EntrenadorAdminController extends Controller
{
    public function index()
    {
        return EntrenadorResource::collection(Entrenador::with('equipoActual')->orderBy('nombre')->get());
    }

    public function show(Entrenador $entrenador)
    {
        return response()->json(['data' => $entrenador]);
    }

    public function store(EntrenadorRequest $request)
    {
        $entrenador = Entrenador::create($request->validated());

        return new EntrenadorResource($entrenador);
    }

    public function update(EntrenadorRequest $request, Entrenador $entrenador)
    {
        $entrenador->update($request->validated());

        return new EntrenadorResource($entrenador);
    }

    public function destroy(Entrenador $entrenador)
    {
        $entrenador->delete();

        return response()->json(['message' => 'Entrenador eliminado.']);
    }
}