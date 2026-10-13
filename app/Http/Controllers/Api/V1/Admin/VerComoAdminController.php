<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

/** GET /admin/ver-como/usuarios — a quién se puede mirar "como él". */
class VerComoAdminController extends Controller
{
    public function usuarios()
    {
        $usuarios = User::with('ligaActiva')
            ->orderByRaw('COALESCE(nombre_visible, name)')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nombre' => $u->nombre_visible ?? $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url ? url($u->avatar_url) : null,
                'liga_activa' => $u->ligaActiva?->nombre,
                'es_superadmin' => (bool) $u->es_superadmin,
            ]);

        return response()->json(['data' => $usuarios]);
    }
}
