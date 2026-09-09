<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopiarPronosticosController extends Controller
{
    public function copiar(Request $request, int $jornada)
    {
        $validated = $request->validate([
            'id_liga_origen' => ['required', 'exists:ligas,id'],
        ]);

        $ligaDestino = $request->user()->ligaActiva;

        if (! $ligaDestino) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $ligaOrigen = Liga::find($validated['id_liga_origen']);

        $esMiembroOrigen = $ligaOrigen->usuarios()->where('id_usuario', $request->user()->id)->exists();
        if (! $esMiembroOrigen) {
            return response()->json(['message' => 'No perteneces a esa liga.'], 403);
        }

        if ($ligaOrigen->id === $ligaDestino->id) {
            return response()->json(['message' => 'La liga de origen no puede ser la misma que la actual.'], 422);
        }

        if (CalendarioPartido::jornadaBloqueada($ligaDestino->id_temporada, $jornada)) {
            return response()->json(['message' => 'Esta jornada ya no admite cambios en tu liga actual.'], 422);
        }

        $idsPartidosOrigen = CalendarioPartido::where('id_temporada', $ligaOrigen->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id', 'id');

        $idsPartidosDestino = CalendarioPartido::where('id_temporada', $ligaDestino->id_temporada)
            ->where('jornada', $jornada)
            ->get()
            ->keyBy(fn ($p) => "{$p->id_equipo_local}-{$p->id_equipo_visitante}");

        $pronosticosOrigen = Pronostico::where('id_liga', $ligaOrigen->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('id_partido', $idsPartidosOrigen)
            ->with('partido')
            ->get();

        $copiadosPronosticos = 0;

        DB::transaction(function () use ($pronosticosOrigen, $idsPartidosDestino, $ligaDestino, $request, &$copiadosPronosticos) {
            foreach ($pronosticosOrigen as $pronostico) {
                $clave = "{$pronostico->partido->id_equipo_local}-{$pronostico->partido->id_equipo_visitante}";
                $partidoDestino = $idsPartidosDestino->get($clave);

                if (! $partidoDestino) {
                    continue;
                }

                Pronostico::updateOrCreate(
                    [
                        'id_usuario' => $request->user()->id,
                        'id_liga' => $ligaDestino->id,
                        'id_partido' => $partidoDestino->id,
                    ],
                    [
                        'resultado_1x2' => $pronostico->resultado_1x2,
                        'goles_local_predicho' => $pronostico->goles_local_predicho,
                        'goles_visitante_predicho' => $pronostico->goles_visitante_predicho,
                        'enviado_en' => now(),
                    ]
                );

                $copiadosPronosticos++;
            }
        });

        $goleadoresOrigen = GoleadorJornada::where('id_liga', $ligaOrigen->id)
            ->where('id_usuario', $request->user()->id)
            ->where('jornada', $jornada)
            ->pluck('id_jugador');

        $copiadosGoleadores = 0;

        if ($goleadoresOrigen->isNotEmpty()) {
            GoleadorJornada::where('id_liga', $ligaDestino->id)
                ->where('id_usuario', $request->user()->id)
                ->where('jornada', $jornada)
                ->delete();

            foreach ($goleadoresOrigen as $idJugador) {
                GoleadorJornada::create([
                    'id_usuario' => $request->user()->id,
                    'id_liga' => $ligaDestino->id,
                    'jornada' => $jornada,
                    'id_jugador' => $idJugador,
                ]);
                $copiadosGoleadores++;
            }
        }

        return response()->json([
            'message' => "Copiado: {$copiadosPronosticos} pronóstico(s) y {$copiadosGoleadores} goleador(es) desde {$ligaOrigen->nombre}.",
        ]);
    }
}