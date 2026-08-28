<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\EventoPartido;
use App\Models\Pronostico;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    public function porJornada(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal.estadio', 'equipoVisitante', 'estadio', 'arbitro'])
            ->orderBy('horario_estimado')
            ->get();

        $misPronosticos = Pronostico::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->whereIn('id_partido', $partidos->pluck('id'))
            ->get()
            ->keyBy('id_partido');

        $datos = $partidos->map(function ($p) use ($misPronosticos) {
            $pronostico = $misPronosticos->get($p->id);

            return [
                'id' => $p->id,
                'jornada' => $p->jornada,
                'equipo_local' => ['id' => $p->equipoLocal->id, 'nombre' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre, 'escudo_url' => $p->equipoLocal->escudo_url],
                'equipo_visitante' => ['id' => $p->equipoVisitante->id, 'nombre' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre, 'escudo_url' => $p->equipoVisitante->escudo_url],
                'estadio' => $p->estadio?->nombre ?? $p->equipoLocal->estadio?->nombre,
                'arbitro' => $p->arbitro ? trim("{$p->arbitro->nombre} {$p->arbitro->apellidos}") : null,
                'horario_estimado' => $p->horario_estimado?->toIso8601String(),
                'estado' => $p->estado,
                'goles_casa' => $p->goles_casa,
                'goles_fuera' => $p->goles_fuera,
                'mi_pronostico' => $pronostico ? [
                    'goles_local_predicho' => $pronostico->goles_local_predicho,
                    'goles_visitante_predicho' => $pronostico->goles_visitante_predicho,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $datos,
            'meta' => ['ultima_actualizacion' => $partidos->max('updated_at')?->toIso8601String()],
        ]);
    }

    public function show(Request $request, CalendarioPartido $partido)
    {
        $partido->load(['equipoLocal.estadio', 'equipoVisitante', 'estadio', 'arbitro']);

        $eventos = EventoPartido::where('id_partido', $partido->id)
            ->with(['jugador', 'jugadorRelacionado', 'equipo'])
            ->get()
            ->sortBy(fn ($e) => (int) $e->minuto)
            ->map(fn ($e) => [
                'minuto' => $e->minuto,
                'tipo_evento' => $e->tipo_evento,
                'jugador' => $e->jugador ? trim("{$e->jugador->nombre} {$e->jugador->apellidos}") : null,
                'jugador_relacionado' => $e->jugadorRelacionado ? trim("{$e->jugadorRelacionado->nombre} {$e->jugadorRelacionado->apellidos}") : null,
                'id_equipo' => $e->id_equipo,
            ])
            ->values();

        return response()->json([
            'data' => [
                'id' => $partido->id,
                'jornada' => $partido->jornada,
                'equipo_local' => ['id' => $partido->equipoLocal->id, 'nombre' => $partido->equipoLocal->nombre_corto ?? $partido->equipoLocal->nombre, 'escudo_url' => $partido->equipoLocal->escudo_url],
                'equipo_visitante' => ['id' => $partido->equipoVisitante->id, 'nombre' => $partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre, 'escudo_url' => $partido->equipoVisitante->escudo_url],
                'estadio' => $partido->estadio?->nombre ?? $partido->equipoLocal->estadio?->nombre,
                'arbitro' => $partido->arbitro ? trim("{$partido->arbitro->nombre} {$partido->arbitro->apellidos}") : null,
                'horario_estimado' => $partido->horario_estimado?->toIso8601String(),
                'estado' => $partido->estado,
                'goles_casa' => $partido->goles_casa,
                'goles_fuera' => $partido->goles_fuera,
                'eventos' => $eventos,
            ],
        ]);
    }
}