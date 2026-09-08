<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;
use App\Models\Temporada;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['Normal', 'ConExtras'])],
            'lema' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'id_usuario_creador' => ['required', 'exists:users,id'],
        ]);

        $temporada = Temporada::orderBy('id')->first();

        $liga = Liga::create([
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'lema' => $validated['lema'] ?? null,
            'logo_url' => $validated['logo_url'] ?? null,
            'codigo_acceso' => $this->generarCodigoUnico(),
            'id_temporada' => $temporada->id,
            'id_usuario_creador' => $validated['id_usuario_creador'],
        ]);

        $liga->usuarios()->attach($validated['id_usuario_creador'], ['rol' => 'Admin']);

        return response()->json(['data' => $liga], 201);
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

    private function generarCodigoUnico(): string
    {
        do {
            $codigo = Str::upper(Str::random(6));
        } while (Liga::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }
}