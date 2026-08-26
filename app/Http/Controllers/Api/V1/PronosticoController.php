<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PronosticoResource;
use App\Models\CalendarioPartido;
use App\Models\Liga;
use App\Models\Pronostico;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PronosticoController extends Controller
{
    public function store(Request $request, Liga $liga)
    {
        $esMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esta liga.'], 403);
        }

        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
            'resultado_1x2' => ['required', Rule::in(['Local', 'Empate', 'Visitante'])],
            'goles_local_predicho' => ['required', 'integer', 'min:0'],
            'goles_visitante_predicho' => ['required', 'integer', 'min:0'],
        ]);

        $partido = CalendarioPartido::findOrFail($validated['id_partido']);

        if ($partido->estado !== 'Programado') {
            return response()->json(['message' => 'Este partido ya no admite pronósticos.'], 422);
        }

        $pronostico = Pronostico::updateOrCreate(
            [
                'id_usuario' => $request->user()->id,
                'id_liga' => $liga->id,
                'id_partido' => $validated['id_partido'],
            ],
            [
                'resultado_1x2' => $validated['resultado_1x2'],
                'goles_local_predicho' => $validated['goles_local_predicho'],
                'goles_visitante_predicho' => $validated['goles_visitante_predicho'],
                'enviado_en' => now(),
            ]
        );

        return new PronosticoResource($pronostico);
    }

    public function misPronosticos(Request $request, Liga $liga, int $jornada)
    {
        $esMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esta liga.'], 403);
        }

        $pronosticos = Pronostico::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->whereHas('partido', fn ($q) => $q->where('jornada', $jornada))
            ->get();

        return PronosticoResource::collection($pronosticos);
    }
}