<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\GoleadorJornada;
use App\Models\Jugador;
use App\Services\MotorFaltas;
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

    /**
     * Mismas 2 reglas que ya validaba store() (no repetir de la jornada
     * anterior, Falta clamorosa activa) — expuestas ANTES de guardar, para que
     * el frontend pueda avisar mientras buscas en vez de solo al fallar el guardado.
     */
    public function bloqueados(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $motivos = [];

        $jugadoresAnteriores = GoleadorJornada::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->where('jornada', $jornada - 1)
            ->pluck('id_jugador');

        foreach ($jugadoresAnteriores as $id) {
            $motivos[$id] = 'Lo elegiste la jornada anterior';
        }

        $motorFaltas = app(MotorFaltas::class);

        if ($motorFaltas->tieneFalloClamorosoActivo($liga->id, $request->user()->id, $jornada)) {
            foreach ($motorFaltas->topGoleadoresRealesIds(3) as $id) {
                $motivos[$id] = 'Falta activa: no puedes elegir a los máximos goleadores reales de LaLiga';
            }
        }

        return response()->json(['data' => $motivos]);
    }

    public function store(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'jugadores' => ['required', 'array', 'min:1', 'max:5'],
            'jugadores.*' => ['required', 'integer', 'distinct', 'exists:jugadores,id'],
        ]);

        if (CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada)) {
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

        // Falta "Fallo clamoroso" activa contra ti: no puedes elegir a los 3
        // máximos goleadores REALES de LaLiga esta temporada.
        $motorFaltas = app(MotorFaltas::class);

        if ($motorFaltas->tieneFalloClamorosoActivo($liga->id, $request->user()->id, $jornada)) {
            $topGoleadoresReales = $motorFaltas->topGoleadoresRealesIds(3);
            $bloqueados = array_intersect($validated['jugadores'], $topGoleadoresReales);

            if (! empty($bloqueados)) {
                $nombresBloqueados = Jugador::whereIn('id', $bloqueados)->pluck('nombre')->implode(', ');
                return response()->json([
                    'message' => "Tienes una Falta activa: no puedes elegir a los 3 máximos goleadores reales de LaLiga esta jornada: {$nombresBloqueados}.",
                ], 422);
            }
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

        $elegidos = count($validated['jugadores']);
        $completo = $elegidos >= 5;

        return response()->json([
            'message' => $completo
                ? 'Goleadores guardados correctamente.'
                : "Goleadores guardados, pero tu selección está incompleta: has elegido {$elegidos} de 5.",
            'completo' => $completo,
            'elegidos' => $elegidos,
        ]);
    }
}