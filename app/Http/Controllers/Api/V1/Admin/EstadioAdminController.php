<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadioRequest;
use App\Http\Resources\EstadioResource;
use App\Models\Estadio;

class EstadioAdminController extends Controller
{
    public function index()
    {
        return EstadioResource::collection(Estadio::orderBy('nombre')->get());
    }

    public function show(Estadio $estadio)
    {
        return response()->json(['data' => $estadio]);
    }

    public function store(EstadioRequest $request)
    {
        $estadio = Estadio::create($request->validated());

        return new EstadioResource($estadio);
    }

    public function update(EstadioRequest $request, Estadio $estadio)
    {
        $estadio->update($request->validated());

        return new EstadioResource($estadio);
    }

    public function destroy(Estadio $estadio)
    {
        $estadio->delete();

        return response()->json(['message' => 'Estadio eliminado.']);
    }
}