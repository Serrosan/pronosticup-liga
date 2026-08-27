<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;
use Illuminate\Http\Request;

class LigaAdminController extends Controller
{
    public function index()
    {
        $ligas = Liga::withCount('usuarios')->with('usuarioCreador')->orderBy('nombre')->get();

        return response()->json([
            'data' => $ligas->map(fn ($liga) => [
                'id' => $liga->id,
                'nombre' => $liga->nombre,
                'codigo_acceso' => $liga->codigo_acceso,
                'tipo' => $liga->tipo,
                'total_miembros' => $liga->usuarios_count,
                'creador' => $liga->usuarioCreador?->name,
            ]),
        ]);
    }

    public function show(Liga $liga)
    {
        return response()->json(['data' => $liga]);
    }

    public function update(Request $request, Liga $liga)
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'lema' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $liga->update($validated);

        return response()->json(['data' => $liga]);
    }

    public function destroy(Liga $liga)
    {
        $liga->delete();

        return response()->json(['message' => 'Liga eliminada.']);
    }
}