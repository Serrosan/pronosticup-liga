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

        $jornadaBloqueada = CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada);

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
                'minuto_partido' => $p->minuto_partido,
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
            'meta' => [
                'ultima_actualizacion' => $partidos->max('sincronizado_en')?->toIso8601String(),
                'jornada_bloqueada' => $jornadaBloqueada,
            ],
        ]);
    }

    public function show(Request $request, CalendarioPartido $partido)
    {
        $partido->load(['equipoLocal.estadio', 'equipoVisitante', 'estadio', 'arbitro']);

        $estadio = $partido->estadio ?? $partido->equipoLocal->estadio;

        $eventos = EventoPartido::where('id_partido', $partido->id)
            ->with(['jugador', 'jugadorRelacionado', 'equipo'])
            ->get()
            ->sortBy(fn ($e) => (int) $e->minuto)
            ->map(fn ($e) => [
                'minuto' => $e->minuto,
                'tipo_evento' => $e->tipo_evento,
                'jugador' => $e->jugador ? trim("{$e->jugador->nombre} {$e->jugador->apellidos}") : null,
                'jugador_foto' => $e->jugador?->foto_url,
                'jugador_relacionado' => $e->jugadorRelacionado ? trim("{$e->jugadorRelacionado->nombre} {$e->jugadorRelacionado->apellidos}") : null,
                'id_equipo' => $e->id_equipo,
            ])
            ->values();

        $enfrentamientosDirectos = CalendarioPartido::where('estado', 'Jugado')
            ->where(function ($q) use ($partido) {
                $q->where(function ($q2) use ($partido) {
                    $q2->where('id_equipo_local', $partido->id_equipo_local)
                        ->where('id_equipo_visitante', $partido->id_equipo_visitante);
                })->orWhere(function ($q2) use ($partido) {
                    $q2->where('id_equipo_local', $partido->id_equipo_visitante)
                        ->where('id_equipo_visitante', $partido->id_equipo_local);
                });
            })
            ->where('id', '!=', $partido->id)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderByDesc('horario_estimado')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                'escudo_local' => $p->equipoLocal->escudo_url,
                'escudo_visitante' => $p->equipoVisitante->escudo_url,
                'goles_casa' => $p->goles_casa,
                'goles_fuera' => $p->goles_fuera,
                'fecha' => $p->horario_estimado?->format('d/m/Y'),
            ]);

        return response()->json([
            'data' => [
                'id' => $partido->id,
                'jornada' => $partido->jornada,
                'equipo_local' => ['id' => $partido->equipoLocal->id, 'nombre' => $partido->equipoLocal->nombre_corto ?? $partido->equipoLocal->nombre, 'escudo_url' => $partido->equipoLocal->escudo_url],
                'equipo_visitante' => ['id' => $partido->equipoVisitante->id, 'nombre' => $partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre, 'escudo_url' => $partido->equipoVisitante->escudo_url],
                'estadio' => $estadio?->nombre,
                'ciudad' => $estadio?->ciudad,
                'arbitro' => $partido->arbitro ? trim("{$partido->arbitro->nombre} {$partido->arbitro->apellidos}") : null,
                'horario_estimado' => $partido->horario_estimado?->toIso8601String(),
                'estado' => $partido->estado,
                'minuto_partido' => $partido->minuto_partido,
                'goles_casa' => $partido->goles_casa,
                'goles_fuera' => $partido->goles_fuera,
                'eventos' => $eventos,
                'video_resumen_url' => $partido->video_resumen_url,
                'actualizado_en' => $partido->sincronizado_en?->toIso8601String() ?? $partido->updated_at->toIso8601String(),
                'enfrentamientos_directos' => $enfrentamientosDirectos,
            ],
        ]);
    }
}
