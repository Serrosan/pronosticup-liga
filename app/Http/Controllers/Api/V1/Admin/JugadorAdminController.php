<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\JugadorRequest;
use App\Http\Resources\JugadorResource;
use App\Models\Jugador;

class JugadorAdminController extends Controller
{
    public function index()
    {
        return JugadorResource::collection(Jugador::orderBy('nombre')->get());
    }

    public function show(Jugador $jugador)
    {
        return response()->json(['data' => $jugador]);
    }

    public function store(JugadorRequest $request)
    {
        $jugador = Jugador::create($request->validated());

        return new JugadorResource($jugador);
    }

    public function update(JugadorRequest $request, Jugador $jugador)
    {
        $jugador->update($request->validated());

        return new JugadorResource($jugador);
    }

    public function destroy(Jugador $jugador)
    {
        $jugador->delete();

        return response()->json(['message' => 'Jugador eliminado.']);
    }
}