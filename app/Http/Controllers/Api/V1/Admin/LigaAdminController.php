<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;

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

    public function destroy(Liga $liga)
    {
        $liga->delete();

        return response()->json(['message' => 'Liga eliminada.']);
    }
}