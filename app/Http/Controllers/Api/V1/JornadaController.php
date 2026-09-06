<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\ConfiguracionPuntos;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Pronostico;
use App\Notifications\JornadaCerradaConPuntos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JornadaController extends Controller
{
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
            ->get();

        $pendientes = $partidos->where('estado', '!=', 'Jugado')->count();
        if ($pendientes > 0) {
            return response()->json(['message' => "Aún hay {$pendientes} partido(s) sin jugar en esta jornada."], 422);
        }

        $config = ConfiguracionPuntos::paraLiga($liga->id);
        $totalPartidos = $partidos->count();

        $puntosPorUsuario = DB::transaction(function () use ($liga, $jornada, $partidos, $config, $totalPartidos) {
            $idsPartidos = $partidos->pluck('id');

            $pronosticos = Pronostico::where('id_liga', $liga->id)
                ->whereIn('id_partido', $idsPartidos)
                ->get();

            $puntosPorUsuario = [];
            $aciertosSignoPorUsuario = [];

            foreach ($pronosticos as $pronostico) {
                $partido = $partidos->firstWhere('id', $pronostico->id_partido);
                $resultadoReal = $this->calcularResultado1x2($partido->goles_casa, $partido->goles_fuera);
                $aciertaSigno = $resultadoReal === $pronostico->resultado_1x2;

                $exactoReal = $partido->goles_casa === $pronostico->goles_local_predicho
                    && $partido->goles_fuera === $pronostico->goles_visitante_predicho;

                $diferenciaReal = $partido->goles_casa - $partido->goles_fuera;
                $diferenciaPredicha = $pronostico->goles_local_predicho - $pronostico->goles_visitante_predicho;
                $aciertaDiferencia = $aciertaSigno && $diferenciaReal === $diferenciaPredicha;

                if ($exactoReal) {
                    $tipo = 'AciertoExacto';
                    $puntos = $config->puntos_exacto;
                } elseif ($aciertaDiferencia) {
                    $tipo = 'AciertoDiferencia';
                    $puntos = $config->puntos_diferencia;
                } elseif ($aciertaSigno) {
                    $tipo = 'Acierto1x2';
                    $puntos = $config->puntos_signo;
                } else {
                    $tipo = 'Fallo';
                    $puntos = 0;
                }

                EventoPuntos::create([
                    'id_usuario' => $pronostico->id_usuario,
                    'id_liga' => $liga->id,
                    'id_partido' => $partido->id,
                    'jornada' => $jornada,
                    'tipo_evento' => $tipo,
                    'puntos' => $puntos,
                ]);

                $puntosPorUsuario[$pronostico->id_usuario] = ($puntosPorUsuario[$pronostico->id_usuario] ?? 0) + $puntos;

                if ($aciertaSigno) {
                    $aciertosSignoPorUsuario[$pronostico->id_usuario] = ($aciertosSignoPorUsuario[$pronostico->id_usuario] ?? 0) + 1;
                }
            }

            foreach ($aciertosSignoPorUsuario as $idUsuario => $aciertos) {
                $bonus = $this->calcularBonusPleno($aciertos, $totalPartidos, $config);

                if ($bonus > 0) {
                    EventoPuntos::create([
                        'id_usuario' => $idUsuario,
                        'id_liga' => $liga->id,
                        'id_partido' => null,
                        'jornada' => $jornada,
                        'tipo_evento' => 'BonusPleno',
                        'puntos' => $bonus,
                    ]);

                    $puntosPorUsuario[$idUsuario] = ($puntosPorUsuario[$idUsuario] ?? 0) + $bonus;
                }
            }

            CierreJornada::updateOrCreate(
                ['id_liga' => $liga->id, 'jornada' => $jornada],
                ['cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => auth()->id()]
            );

            return $puntosPorUsuario;
        });

        $this->notificarCierre($liga, $jornada, $puntosPorUsuario);

        $totalEventos = EventoPuntos::where('id_liga', $liga->id)->where('jornada', $jornada)->count();

        return response()->json([
            'message' => 'Jornada cerrada. Puntos de signo/diferencia/exacto calculados. Recuerda recalcular los goleadores cuando termines de cargar los eventos del partido.',
            'eventos_creados' => $totalEventos,
        ]);
    }

    public function recalcularEventos(Request $request, int $jornada)
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
            return response()->json(['message' => 'Solo el admin de la liga puede recalcular esto.'], 403);
        }

        $yaCerrada = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();
        if (! $yaCerrada) {
            return response()->json(['message' => 'Esta jornada aún no está cerrada. Ciérrala primero.'], 422);
        }

        $config = ConfiguracionPuntos::paraLiga($liga->id);

        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $creados = DB::transaction(function () use ($liga, $jornada, $idsPartidos, $config) {
            // Idempotente: borramos lo que ya existiera de esta categoría en esta jornada, para poder recalcular sin duplicar
            EventoPuntos::where('id_liga', $liga->id)
                ->where('jornada', $jornada)
                ->where('tipo_evento', 'GolesGoleadorElegido')
                ->delete();

            $golesPorJugador = EventoPartido::whereIn('id_partido', $idsPartidos)
                ->where('tipo_evento', 'gol')
                ->get()
                ->countBy('id_jugador');

            $seleccionesGoleadores = GoleadorJornada::where('id_liga', $liga->id)
                ->where('jornada', $jornada)
                ->get();

            $creados = 0;

            foreach ($seleccionesGoleadores as $seleccion) {
                $golesDeEseJugador = $golesPorJugador->get($seleccion->id_jugador, 0);

                if ($golesDeEseJugador > 0) {
                    $puntosGoleador = $golesDeEseJugador * $config->puntos_gol_goleador;

                    EventoPuntos::create([
                        'id_usuario' => $seleccion->id_usuario,
                        'id_liga' => $liga->id,
                        'id_partido' => null,
                        'jornada' => $jornada,
                        'tipo_evento' => 'GolesGoleadorElegido',
                        'puntos' => $puntosGoleador,
                    ]);

                    $creados++;
                }
            }

            return $creados;
        });

        return response()->json([
            'message' => "Recalculado. {$creados} usuario(s) recibieron puntos de goleadores con los eventos disponibles ahora mismo.",
        ]);
    }

    private function calcularBonusPleno(int $aciertos, int $totalPartidos, ConfiguracionPuntos $config): int
    {
        if ($totalPartidos <= 0) {
            return 0;
        }

        $porcentaje = ($aciertos / $totalPartidos) * 100;

        if ($aciertos === $totalPartidos) {
            return $config->bonus_pleno_10;
        }
        if ($porcentaje >= 90) {
            return $config->bonus_pleno_9;
        }
        if ($porcentaje >= 80) {
            return $config->bonus_pleno_8;
        }
        if ($porcentaje >= 70) {
            return $config->bonus_pleno_7;
        }

        return 0;
    }

    private function notificarCierre($liga, int $jornada, array $puntosPorUsuario): void
    {
        $clasificacion = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('id_usuario, SUM(puntos) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->get();

        foreach ($liga->usuarios as $usuario) {
            if (! isset($puntosPorUsuario[$usuario->id])) {
                continue;
            }

            $posicion = $clasificacion->search(fn ($fila) => $fila->id_usuario === $usuario->id);

            $usuario->notify(new JornadaCerradaConPuntos(
                $jornada,
                $puntosPorUsuario[$usuario->id],
                $posicion === false ? null : $posicion + 1,
            ));
        }
    }

    private function calcularResultado1x2(int $golesCasa, int $golesFuera): string
    {
        if ($golesCasa > $golesFuera) return 'Local';
        if ($golesCasa < $golesFuera) return 'Visitante';
        return 'Empate';
    }
}