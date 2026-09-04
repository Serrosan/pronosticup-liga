<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;

class JugadorController extends Controller
{
    public function show(Jugador $jugador)
    {
        $plantillaActual = PlantillaTemporada::where('id_jugador', $jugador->id)
            ->whereNull('fecha_salida')
            ->with('equipo')
            ->first();

        $historial = PlantillaTemporada::where('id_jugador', $jugador->id)
            ->with('equipo')
            ->orderByDesc('fecha_incorporacion')
            ->get()
            ->map(fn ($p) => [
                'equipo' => $p->equipo->nombre_corto ?? $p->equipo->nombre,
                'escudo_url' => $p->equipo->escudo_url,
                'dorsal' => $p->dorsal,
                'fecha_incorporacion' => $p->fecha_incorporacion,
                'fecha_salida' => $p->fecha_salida,
                'actual' => is_null($p->fecha_salida),
            ]);

        $eventos = EventoPartido::where('id_jugador', $jugador->id)->get();
        $asistencias = EventoPartido::where('id_jugador_relacionado', $jugador->id)
            ->where('tipo_evento', 'gol')
            ->count();

        return response()->json([
            'data' => [
                'id' => $jugador->id,
                'nombre' => $jugador->nombre,
                'apellidos' => $jugador->apellidos,
                'nombre_camiseta' => $jugador->nombre_camiseta,
                'posicion' => $jugador->posicion,
                'posicion_detallada' => $jugador->posicion_detallada,
                'pie' => $jugador->pie,
                'nacionalidad' => $jugador->nacionalidad,
                'fecha_nacimiento' => $jugador->fecha_nacimiento?->format('Y-m-d'),
                'lugar_nacimiento' => $jugador->lugar_nacimiento,
                'seleccion' => $jugador->seleccion,
                'altura' => $jugador->altura,
                'foto_url' => $jugador->foto_url,
                'club_anterior' => $jugador->club_anterior,
                'dado_de_baja' => ! is_null($jugador->dado_de_baja_en),
                'equipo_actual' => $plantillaActual ? [
                    'id' => $plantillaActual->equipo->id,
                    'nombre' => $plantillaActual->equipo->nombre_corto ?? $plantillaActual->equipo->nombre,
                    'escudo_url' => $plantillaActual->equipo->escudo_url,
                    'color_primario' => $plantillaActual->equipo->color_primario,
                    'dorsal' => $plantillaActual->dorsal,
                ] : null,
                'historial' => $historial,
                'stats' => [
                    'goles' => $eventos->where('tipo_evento', 'gol')->count(),
                    'asistencias' => $asistencias,
                    'tarjetas_amarillas' => $eventos->where('tipo_evento', 'tarjeta_amarilla')->count(),
                    'tarjetas_rojas' => $eventos->where('tipo_evento', 'tarjeta_roja')->count(),
                ],
            ],
        ]);
    }
}