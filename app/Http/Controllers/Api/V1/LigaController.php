<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LigaResource;
use App\Models\Liga;
use App\Models\Temporada;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LigaController extends Controller
{
    public function index(Request $request)
    {
        $ligas = $request->user()->ligas()->withCount('usuarios')->get();

        return LigaResource::collection($ligas);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['Normal', 'ConExtras'])],
        ]);

        $temporada = Temporada::latest('id')->first();

        $liga = Liga::create([
            'nombre' => $validated['nombre'],
            'tipo' => $validated['tipo'],
            'codigo_acceso' => $this->generarCodigoUnico(),
            'id_temporada' => $temporada->id,
            'id_usuario_creador' => $request->user()->id,
        ]);

        $liga->usuarios()->attach($request->user()->id, ['rol' => 'Admin']);

        return (new LigaResource($liga->load('temporada')))
            ->response()
            ->setStatusCode(201);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'codigo_acceso' => ['required', 'string'],
        ]);

        $liga = Liga::where('codigo_acceso', strtoupper($validated['codigo_acceso']))->first();

        if (! $liga) {
            return response()->json(['message' => 'Código de acceso no válido.'], 404);
        }

        $yaEsMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if ($yaEsMiembro) {
            return response()->json(['message' => 'Ya perteneces a esta liga.'], 409);
        }

        $liga->usuarios()->attach($request->user()->id, ['rol' => 'Miembro']);

        return new LigaResource($liga->load('temporada'));
    }

    public function show(Request $request, Liga $liga)
    {
        $esMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esta liga.'], 403);
        }

        return new LigaResource($liga->load('temporada')->loadCount('usuarios'));
    }

    private function generarCodigoUnico(): string
    {
        do {
            $codigo = Str::upper(Str::random(6));
        } while (Liga::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }
}