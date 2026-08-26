<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrofeoResource;
use App\Models\Trofeo;
use Illuminate\Http\Request;

class TrofeoAdminController extends Controller
{
    public function index()
    {
        return TrofeoResource::collection(Trofeo::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $trofeo = Trofeo::create($this->validado($request));

        return new TrofeoResource($trofeo);
    }

    public function update(Request $request, Trofeo $trofeo)
    {
        $trofeo->update($this->validado($request));

        return new TrofeoResource($trofeo);
    }

    public function destroy(Trofeo $trofeo)
    {
        $trofeo->delete();

        return response()->json(['message' => 'Trofeo eliminado.']);
    }

    private function validado(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:100'],
            'ambito' => ['nullable', 'string', 'max:100'],
        ]);
    }
}