<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadioResource;
use App\Models\Estadio;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $estadio = Estadio::create($this->validado($request));

        return new EstadioResource($estadio);
    }

    public function update(Request $request, Estadio $estadio)
    {
        $estadio->update($this->validado($request));

        return new EstadioResource($estadio);
    }

    public function destroy(Estadio $estadio)
    {
        $estadio->delete();

        return response()->json(['message' => 'Estadio eliminado.']);
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'capacidad' => ['nullable', 'integer'],
            'tamanio_campo' => ['nullable', 'string', 'max:50'],
            'anio_construccion' => ['nullable', 'integer'],
            'anio_ult_remodelacion' => ['nullable', 'integer'],
        ]);
    }
}