<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\EventoCalendario;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function mes(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'anio' => ['required', 'integer'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $inicioMes = "{$validated['anio']}-{$validated['mes']}-01";
        $finMes = date('Y-m-t', strtotime($inicioMes));

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereBetween('horario_estimado', ["{$inicioMes} 00:00:00", "{$finMes} 23:59:59"])
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get();

        $partidosPorDia = $partidos->groupBy(fn ($p) => $p->horario_estimado->format('Y-m-d'))
            ->map(fn ($grupo) => $grupo->map(fn ($p) => [
                'id' => $p->id,
                'jornada' => $p->jornada,
                'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                'escudo_local' => $p->equipoLocal->escudo_url,
                'escudo_visitante' => $p->equipoVisitante->escudo_url,
                'hora' => $p->horario_estimado->format('H:i'),
                'estado' => $p->estado,
            ])->values());

        $jornadasPorDia = $partidos->groupBy(fn ($p) => $p->horario_estimado->format('Y-m-d'))
            ->map(fn ($grupo) => $grupo->pluck('jornada')->unique()->values());

        $eventos = EventoCalendario::where('fecha_inicio', '<=', $finMes)
            ->where('fecha_fin', '>=', $inicioMes)
            ->get();

        return response()->json([
            'data' => [
                'jornadas_por_dia' => $jornadasPorDia,
                'partidos_por_dia' => $partidosPorDia,
                'eventos' => $eventos,
            ],
        ]);
    }
}