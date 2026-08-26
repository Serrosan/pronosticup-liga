<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArbitroResource;
use App\Models\Arbitro;
use Illuminate\Http\Request;

class ArbitroAdminController extends Controller
{
    public function index()
    {
        return ArbitroResource::collection(Arbitro::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $arbitro = Arbitro::create($this->validado($request));

        return new ArbitroResource($arbitro);
    }

    public function update(Request $request, Arbitro $arbitro)
    {
        $arbitro->update($this->validado($request));

        return new ArbitroResource($arbitro);
    }

    public function destroy(Arbitro $arbitro)
    {
        $arbitro->delete();

        return response()->json(['message' => 'Árbitro eliminado.']);
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'comunidad_autonoma' => ['nullable', 'string', 'max:255'],
        ]);
    }
}