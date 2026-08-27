<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PronosticoResource;
use App\Models\CalendarioPartido;
use App\Models\EventoPuntos;
use App\Models\Pronostico;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PronosticoController extends Controller
{
    public function store(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
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

    public function misPronosticos(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $pronosticos = Pronostico::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->whereHas('partido', fn ($q) => $q->where('jornada', $jornada))
            ->get();

        return PronosticoResource::collection($pronosticos);
    }

    public function todos(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $userId = $request->user()->id;

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->with(['partido.equipoLocal', 'partido.equipoVisitante'])
            ->get();

        $idsPartidos = $pronosticos->pluck('id_partido');

        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('id_partido', $idsPartidos)
            ->get()
            ->keyBy('id_partido');

        $filas = $pronosticos->map(function ($p) use ($eventos) {
            $evento = $eventos->get($p->id_partido);

            return [
                'id' => $p->id,
                'id_partido' => $p->id_partido,
                'jornada' => $p->partido->jornada,
                'equipo_local' => $p->partido->equipoLocal->nombre_corto ?? $p->partido->equipoLocal->nombre,
                'equipo_visitante' => $p->partido->equipoVisitante->nombre_corto ?? $p->partido->equipoVisitante->nombre,
                'escudo_local' => $p->partido->equipoLocal->escudo_url,
                'escudo_visitante' => $p->partido->equipoVisitante->escudo_url,
                'horario_estimado' => $p->partido->horario_estimado?->format('d/m/Y H:i'),
                'estado_partido' => $p->partido->estado,
                'goles_casa' => $p->partido->goles_casa,
                'goles_fuera' => $p->partido->goles_fuera,
                'mi_pronostico' => "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}",
                'resultado_1x2' => $p->resultado_1x2,
                'puntos' => $evento?->puntos,
                'tipo_evento' => $evento?->tipo_evento,
            ];
        })->sortByDesc('jornada')->values();

        return response()->json([
            'data' => [
                'stats' => [
                    'total' => $filas->count(),
                    'puntos_totales' => (int) $filas->sum('puntos'),
                    'aciertos' => $filas->whereIn('tipo_evento', ['AciertoExacto', 'Acierto1x2'])->count(),
                    'exactos' => $filas->where('tipo_evento', 'AciertoExacto')->count(),
                ],
                'pronosticos' => $filas,
            ],
        ]);
    }
}