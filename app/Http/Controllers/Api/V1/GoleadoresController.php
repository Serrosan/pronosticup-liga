<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\GoleadorJornada;
use App\Models\Jugador;
use Illuminate\Http\Request;

class GoleadoresController extends Controller
{
    public function show(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $seleccion = GoleadorJornada::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->with('jugador')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id_jugador,
                'nombre' => $g->jugador->nombre_camiseta ?? trim("{$g->jugador->nombre} {$g->jugador->apellidos}"),
                'foto_url' => $g->jugador->foto_url,
            ]);

        return response()->json(['data' => $seleccion]);
    }

    public function store(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'jugadores' => ['required', 'array', 'size:5'],
            'jugadores.*' => ['required', 'integer', 'distinct', 'exists:jugadores,id'],
        ]);

        $jornadaBloqueada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->where('estado', '!=', 'Programado')
            ->exists();

        if ($jornadaBloqueada) {
            return response()->json(['message' => 'Esta jornada ya no admite cambios en tus goleadores elegidos.'], 422);
        }

        $jugadoresAnteriores = GoleadorJornada::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->where('jornada', $jornada - 1)
            ->pluck('id_jugador')
            ->all();

        $repetidos = array_intersect($validated['jugadores'], $jugadoresAnteriores);

        if (! empty($repetidos)) {
            $nombresRepetidos = Jugador::whereIn('id', $repetidos)->pluck('nombre')->implode(', ');
            return response()->json([
                'message' => "No puedes repetir jugadores de la jornada anterior: {$nombresRepetidos}.",
            ], 422);
        }

        GoleadorJornada::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->delete();

        foreach ($validated['jugadores'] as $idJugador) {
            GoleadorJornada::create([
                'id_usuario' => $request->user()->id,
                'id_liga' => $liga->id,
                'jornada' => $jornada,
                'id_jugador' => $idJugador,
            ]);
        }

        return response()->json(['message' => 'Goleadores guardados correctamente.']);
    }
}