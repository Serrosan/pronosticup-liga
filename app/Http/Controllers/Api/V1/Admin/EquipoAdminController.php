<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $equipo = Equipo::create($this->validado($request));

        return new EquipoResource($equipo);
    }

    public function update(Request $request, Equipo $equipo)
    {
        $equipo->update($this->validado($request));

        return new EquipoResource($equipo);
    }

    public function destroy(Equipo $equipo)
    {
        $equipo->delete();

        return response()->json(['message' => 'Equipo eliminado.']);
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_corto' => ['nullable', 'string', 'max:255'],
            'apodo' => ['nullable', 'string', 'max:255'],
            'siglas' => ['nullable', 'string', 'max:10'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'id_estadio' => ['nullable', 'integer', 'exists:estadios,id'],
            'año_fundacion' => ['nullable', 'integer'],
            'escudo_url' => ['nullable', 'string', 'max:255'],
            'color_primario' => ['nullable', 'string', 'max:50'],
            'color_secundario' => ['nullable', 'string', 'max:50'],
            'num_socios' => ['nullable', 'integer'],
            'num_abonados' => ['nullable', 'integer'],
            'camiseta_1' => ['nullable', 'string', 'max:500'],
            'camiseta_2' => ['nullable', 'string', 'max:500'],
            'camiseta_3' => ['nullable', 'string', 'max:500'],
            'id_externo_api' => ['nullable', 'integer'],
            'id_equipo_api' => ['nullable', 'integer'],
        ]);
    }
}