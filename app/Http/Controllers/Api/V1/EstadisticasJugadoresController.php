<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventoPartido;
use App\Models\Jugador;
use Illuminate\Http\Request;

class EstadisticasJugadoresController extends Controller
{
    public function goleadores(Request $request)
    {
        $conteo = EventoPartido::where('tipo_evento', 'gol')
            ->whereNotNull('id_jugador')
            ->selectRaw('id_jugador, count(*) as goles')
            ->groupBy('id_jugador')
            ->orderByDesc('goles')
            ->limit(50)
            ->get();

        return response()->json(['data' => $this->conFichaJugador($conteo, 'goles')]);
    }

    public function asistencias(Request $request)
    {
        $conteo = EventoPartido::where('tipo_evento', 'gol')
            ->whereNotNull('id_jugador_relacionado')
            ->selectRaw('id_jugador_relacionado as id_jugador, count(*) as asistencias')
            ->groupBy('id_jugador_relacionado')
            ->orderByDesc('asistencias')
            ->limit(50)
            ->get();

        return response()->json(['data' => $this->conFichaJugador($conteo, 'asistencias')]);
    }

    public function tarjetas(Request $request)
    {
        $amarillas = EventoPartido::where('tipo_evento', 'tarjeta_amarilla')
            ->whereNotNull('id_jugador')
            ->selectRaw('id_jugador, count(*) as total')
            ->groupBy('id_jugador')
            ->pluck('total', 'id_jugador');

        $rojas = EventoPartido::where('tipo_evento', 'tarjeta_roja')
            ->whereNotNull('id_jugador')
            ->selectRaw('id_jugador, count(*) as total')
            ->groupBy('id_jugador')
            ->pluck('total', 'id_jugador');

        $idsJugadores = $amarillas->keys()->merge($rojas->keys())->unique();

        $jugadores = Jugador::with('plantillasTemporada.equipo')
            ->whereIn('id', $idsJugadores)
            ->get()
            ->keyBy('id');

        $filas = $idsJugadores->map(function ($id) use ($amarillas, $rojas, $jugadores) {
            $jugador = $jugadores->get($id);
            if (! $jugador) return null;

            $equipoActual = $jugador->plantillasTemporada->whereNull('fecha_salida')->first()?->equipo;

            return [
                'id' => $jugador->id,
                'nombre' => $jugador->nombre_camiseta ?? trim("{$jugador->nombre} {$jugador->apellidos}"),
                'foto_url' => $jugador->foto_url,
                'equipo' => $equipoActual?->nombre_corto ?? $equipoActual?->nombre,
                'escudo_url' => $equipoActual?->escudo_url,
                'amarillas' => $amarillas->get($id, 0),
                'rojas' => $rojas->get($id, 0),
            ];
        })->filter()->sortByDesc(fn ($f) => $f['amarillas'] + ($f['rojas'] * 3))->take(50)->values();

        return response()->json(['data' => $filas]);
    }

    private function conFichaJugador($conteo, string $campo)
    {
        $idsJugadores = $conteo->pluck('id_jugador');

        $jugadores = Jugador::with('plantillasTemporada.equipo')
            ->whereIn('id', $idsJugadores)
            ->get()
            ->keyBy('id');

        return $conteo->map(function ($fila) use ($jugadores, $campo) {
            $jugador = $jugadores->get($fila->id_jugador);
            if (! $jugador) return null;

            $equipoActual = $jugador->plantillasTemporada->whereNull('fecha_salida')->first()?->equipo;

            return [
                'id' => $jugador->id,
                'nombre' => $jugador->nombre_camiseta ?? trim("{$jugador->nombre} {$jugador->apellidos}"),
                'foto_url' => $jugador->foto_url,
                'equipo' => $equipoActual?->nombre_corto ?? $equipoActual?->nombre,
                'escudo_url' => $equipoActual?->escudo_url,
                $campo => $fila->$campo,
            ];
        })->filter()->values();
    }
}