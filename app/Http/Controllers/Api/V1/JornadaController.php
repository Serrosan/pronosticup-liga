<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\EventoPuntos;
use App\Models\Pronostico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JornadaController extends Controller
{
    private const PUNTOS_EXACTO = 3;
    private const PUNTOS_1X2 = 1;
    private const PUNTOS_FALLO = 0;

    public function cerrar(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esAdmin = $liga->usuarios()
            ->where('id_usuario', $request->user()->id)
            ->wherePivot('rol', 'Admin')
            ->exists();

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo el admin de la liga puede cerrar la jornada.'], 403);
        }

        $yaCerrada = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();

        if ($yaCerrada) {
            return response()->json(['message' => 'Esta jornada ya estaba cerrada.'], 409);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->where('estado', '!=', 'Aplazado')
            ->get();

        $pendientes = $partidos->where('estado', '!=', 'Jugado')->count();

        if ($pendientes > 0) {
            return response()->json(['message' => "Aún hay {$pendientes} partido(s) sin jugar en esta jornada."], 422);
        }

        DB::transaction(function () use ($liga, $jornada, $partidos) {
            $idsPartidos = $partidos->pluck('id');

            $pronosticos = Pronostico::where('id_liga', $liga->id)
                ->whereIn('id_partido', $idsPartidos)
                ->get();

            foreach ($pronosticos as $pronostico) {
                $partido = $partidos->firstWhere('id', $pronostico->id_partido);

                $resultadoReal = $this->calcularResultado1x2($partido->goles_casa, $partido->goles_fuera);
                $exactoReal = $partido->goles_casa === $pronostico->goles_local_predicho
                    && $partido->goles_fuera === $pronostico->goles_visitante_predicho;

                if ($exactoReal) {
                    $tipo = 'AciertoExacto';
                    $puntos = self::PUNTOS_EXACTO;
                } elseif ($resultadoReal === $pronostico->resultado_1x2) {
                    $tipo = 'Acierto1x2';
                    $puntos = self::PUNTOS_1X2;
                } else {
                    $tipo = 'Fallo';
                    $puntos = self::PUNTOS_FALLO;
                }

                EventoPuntos::create([
                    'id_usuario' => $pronostico->id_usuario,
                    'id_liga' => $liga->id,
                    'id_partido' => $partido->id,
                    'jornada' => $jornada,
                    'tipo_evento' => $tipo,
                    'puntos' => $puntos,
                ]);
            }

            CierreJornada::updateOrCreate(
                ['id_liga' => $liga->id, 'jornada' => $jornada],
                ['cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => auth()->id()]
            );
        });

        $totalEventos = EventoPuntos::where('id_liga', $liga->id)->where('jornada', $jornada)->count();

        return response()->json([
            'message' => 'Jornada cerrada y puntos calculados.',
            'eventos_creados' => $totalEventos,
        ]);
    }

    private function calcularResultado1x2(int $golesCasa, int $golesFuera): string
    {
        if ($golesCasa > $golesFuera) return 'Local';
        if ($golesCasa < $golesFuera) return 'Visitante';
        return 'Empate';
    }
}