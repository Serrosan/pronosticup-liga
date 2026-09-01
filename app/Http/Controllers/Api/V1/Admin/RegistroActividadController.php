<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistroActividad;
use Illuminate\Http\Request;

class RegistroActividadController extends Controller
{
    public function index(Request $request)
    {
        $registros = RegistroActividad::with('usuario')
            ->orderByDesc('creado_en')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'usuario' => $r->usuario?->nombre_visible ?? $r->usuario?->name ?? 'Sistema',
                'accion' => $r->accion,
                'modelo' => $r->modelo,
                'id_registro' => $r->id_registro,
                'cambios' => $r->cambios,
                'creado_en' => $r->creado_en->toIso8601String(),
            ]);

        return response()->json(['data' => $registros]);
    }
}