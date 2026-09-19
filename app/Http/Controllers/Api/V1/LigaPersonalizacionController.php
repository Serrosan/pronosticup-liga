<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImagenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LigaPersonalizacionController extends Controller
{
    public function actualizar(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esAdmin = $liga->usuarios()
            ->where('id_usuario', $request->user()->id)
            ->wherePivot('rol', 'Admin')
            ->exists();

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo el admin de la liga puede personalizarla.'], 403);
        }

        $validated = $request->validate([
            'lema' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $datos = [];

        if ($request->has('lema')) {
            $datos['lema'] = $validated['lema'];
        }

        if ($request->hasFile('logo')) {
            $ruta = $request->file('logo')->store('ligas', 'public');
            ImagenService::comprimir(Storage::disk('public')->path($ruta));
            $datos['logo_url'] = Storage::url($ruta);
        }

        $liga->update($datos);

        return response()->json([
            'data' => [
                'nombre' => $liga->nombre,
                'lema' => $liga->lema,
                'logo_url' => $liga->logo_url ? url($liga->logo_url) : null,
            ],
        ]);
    }

    public function miembros(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $miembros = $liga->usuarios()->get(['users.id', 'users.name', 'users.nombre_visible'])
            ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_visible ?? $u->name]);

        return response()->json(['data' => $miembros]);
    }
}