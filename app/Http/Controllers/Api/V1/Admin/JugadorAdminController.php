<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JugadorResource;
use App\Models\Jugador;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $jugador = Jugador::create($this->validado($request));

        return new JugadorResource($jugador);
    }

    public function update(Request $request, Jugador $jugador)
    {
        $jugador->update($this->validado($request));

        return new JugadorResource($jugador);
    }

    public function destroy(Jugador $jugador)
    {
        $jugador->delete();

        return response()->json(['message' => 'Jugador eliminado.']);
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'nombre_camiseta' => ['nullable', 'string', 'max:255'],
            'posicion' => ['nullable', 'string', 'max:50'],
            'posicion_detallada' => ['nullable', 'string', 'max:100'],
            'pie' => ['nullable', 'string', 'max:50'],
            'nacionalidad' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:255'],
            'seleccion' => ['nullable', 'string', 'max:255'],
            'altura' => ['nullable', 'integer'],
            'fecha_fin_contrato' => ['nullable', 'date'],
            'club_anterior' => ['nullable', 'string', 'max:255'],
            'id_externo_api' => ['nullable', 'integer'],
            'foto_url' => ['nullable', 'string', 'max:500'],
        ]);
    }
}