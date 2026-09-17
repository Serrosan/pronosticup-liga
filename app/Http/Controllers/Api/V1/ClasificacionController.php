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
use App\Models\User;
use Illuminate\Http\Request;

class ClasificacionController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'hasta_jornada' => ['nullable', 'integer', 'min:1'],
        ]);

        $jornadaSeleccionada = $validated['hasta_jornada'] ?? null;

        $eventosCompletos = EventoPuntos::where('id_liga', $liga->id)->with('partido')->get();

        $eventosParaFilas = $jornadaSeleccionada
            ? $eventosCompletos->where('jornada', $jornadaSeleccionada)
            : $eventosCompletos;

        $porUsuario = $eventosParaFilas->groupBy('id_usuario');

        $jornadaParaComparar = $jornadaSeleccionada ?? CierreJornada::where('id_liga', $liga->id)->where('cerrada', true)->max('jornada');

        $posicionesAnteriores = [];
        if ($jornadaParaComparar) {
            $eventosAnteriores = $eventosCompletos->where('jornada', '<', $jornadaParaComparar);
            $posicionesAnteriores = $eventosAnteriores->groupBy('id_usuario')
                ->map(fn ($grupo) => $grupo->sum('puntos'))
                ->sortDesc()
                ->keys()
                ->values()
                ->flip()
                ->toArray();
        }

        $filas = $porUsuario->map(function ($grupo, $idUsuario) {
            $usuario = User::find($idUsuario);

            $eventosConPartido = $grupo->filter(fn ($e) => $e->id_partido && $e->partido)
                ->sortByDesc(fn ($e) => $e->partido->horario_estimado);

            $racha = 0;
            foreach ($eventosConPartido as $evento) {
                if ($evento->tipo_evento === 'Fallo') {
                    break;
                }
                $racha++;
            }

            $aciertos = $grupo->whereIn('tipo_evento', ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'])->count();
            $fallos = $grupo->where('tipo_evento', 'Fallo')->count();
            $resueltos = $aciertos + $fallos;

            return [
                'id_usuario' => $idUsuario,
                'usuario' => $usuario->nombre_visible ?? $usuario->name,
                'avatar_url' => $usuario->avatar_url ? url($usuario->avatar_url) : null,
                'puntos_totales' => (int) $grupo->sum('puntos'),
                'puntos_goleadores' => (int) $grupo->where('tipo_evento', 'GolesGoleadorElegido')->sum('puntos'),
                'aciertos' => $aciertos,
                'fallos' => $fallos,
                'exactos' => $grupo->where('tipo_evento', 'AciertoExacto')->count(),
                'porcentaje_exito' => $resueltos > 0 ? round(($aciertos / $resueltos) * 100) : null,
                'racha_actual' => $racha,
            ];
        })->sortByDesc('puntos_totales')->values();

        $filas = $filas->map(function ($fila, $posicionActual) use ($posicionesAnteriores) {
            $posicionAnterior = $posicionesAnteriores[$fila['id_usuario']] ?? null;

            $fila['tendencia'] = null;
            if (! is_null($posicionAnterior)) {
                if ($posicionAnterior > $posicionActual) {
                    $fila['tendencia'] = 'sube';
                } elseif ($posicionAnterior < $posicionActual) {
                    $fila['tendencia'] = 'baja';
                } else {
                    $fila['tendencia'] = 'igual';
                }
            }

            return $fila;
        });

        return response()->json(['data' => $filas]);
    }

    public function jornadasCerradas(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $jornadas = CierreJornada::where('id_liga', $liga->id)
            ->where('cerrada', true)
            ->orderByDesc('jornada')
            ->pluck('jornada');

        return response()->json(['data' => $jornadas]);
    }

    public function detalle(Request $request, User $usuario)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esMiembro = $liga->usuarios()->where('id_usuario', $usuario->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'Ese usuario no pertenece a tu liga.'], 403);
        }

        $config = ConfiguracionPuntos::paraLiga($liga->id);
        $esUnoMismo = $usuario->id === $request->user()->id;

        // Necesitamos saber, jornada a jornada, si el admin ya pulsó "Recalcular goleadores"
        // de verdad — un simple "0 puntos" no basta, porque 0 real y "aún sin calcular" se ven
        // igual en la base de datos si nadie marcó gol con su goleador elegido esa semana.
        $goleadoresCalculadosPorJornada = CierreJornada::where('id_liga', $liga->id)
            ->get()
            ->keyBy('jornada')
            ->map(fn ($cierre) => ! is_null($cierre->goleadores_calculados_en));

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->with(['partido.equipoLocal', 'partido.equipoVisitante'])
            ->get();

        $idsPartidos = $pronosticos->pluck('id_partido');

        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->whereIn('id_partido', $idsPartidos)
            ->get()
            ->keyBy('id_partido');

        $bonusPlenoPorJornada = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->where('tipo_evento', 'BonusPleno')
            ->get()
            ->groupBy('jornada')
            ->map(fn ($grupo) => (int) $grupo->sum('puntos'));

        $filasPorPartido = $pronosticos->map(function ($p) use ($eventos, $esUnoMismo) {
            $evento = $eventos->get($p->id_partido);

            $jornadaBloqueada = CalendarioPartido::jornadaBloqueada($p->partido->id_temporada, $p->partido->jornada);
            $puedeVerse = $esUnoMismo || $jornadaBloqueada;

            return [
                'id' => $p->id,
                'id_partido' => $p->id_partido,
                'jornada' => $p->partido->jornada,
                'equipo_local' => $p->partido->equipoLocal->nombre_corto ?? $p->partido->equipoLocal->nombre,
                'equipo_visitante' => $p->partido->equipoVisitante->nombre_corto ?? $p->partido->equipoVisitante->nombre,
                'escudo_local' => $p->partido->equipoLocal->escudo_url,
                'escudo_visitante' => $p->partido->equipoVisitante->escudo_url,
                'estado_partido' => $p->partido->estado,
                'goles_casa' => $p->partido->goles_casa,
                'goles_fuera' => $p->partido->goles_fuera,
                'mi_pronostico' => $puedeVerse ? "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}" : null,
                'oculto' => ! $puedeVerse,
                'resultado_1x2' => $p->resultado_1x2,
                'puntos' => $evento?->puntos,
                'tipo_evento' => $evento?->tipo_evento,
            ];
        });

        $numerosJornada = $filasPorPartido->pluck('jornada')->unique()->sortDesc()->values();

        $jornadas = $numerosJornada->map(function ($jornada) use ($liga, $usuario, $filasPorPartido, $bonusPlenoPorJornada, $config, $esUnoMismo, $goleadoresCalculadosPorJornada) {
            $partidosDeEstaJornada = $filasPorPartido->where('jornada', $jornada)->values();

            $bloqueada = CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada);
            $goleadoresVisibles = $esUnoMismo || $bloqueada;
            $goleadoresCalculadosOficial = $goleadoresCalculadosPorJornada->get($jornada, false);

            $idsPartidosJornada = $partidosDeEstaJornada->pluck('id_partido');

            $seleccionGoleadores = GoleadorJornada::where('id_liga', $liga->id)
                ->where('id_usuario', $usuario->id)
                ->where('jornada', $jornada)
                ->with('jugador')
                ->get();

            $golesRealesPorJugador = EventoPartido::whereIn('id_partido', $idsPartidosJornada)
                ->where('tipo_evento', 'gol')
                ->get()
                ->countBy('id_jugador');

            $goleadores = $seleccionGoleadores->map(function ($seleccion) use ($golesRealesPorJugador, $config, $goleadoresVisibles, $goleadoresCalculadosOficial) {
                $goles = $golesRealesPorJugador->get($seleccion->id_jugador, 0);
                $puntosCalculados = $goles * $config->puntos_gol_goleador;

                return [
                    'id' => $goleadoresVisibles ? $seleccion->id_jugador : null,
                    'nombre' => $goleadoresVisibles ? ($seleccion->jugador->nombre_camiseta ?? trim("{$seleccion->jugador->nombre} {$seleccion->jugador->apellidos}")) : null,
                    'foto_url' => $goleadoresVisibles ? $seleccion->jugador->foto_url : null,
                    // Los goles marcados son SIEMPRE informativos, se muestren o no ya los puntos oficiales.
                    'goles' => $goleadoresVisibles ? $goles : null,
                    // Los puntos solo se muestran una vez el admin ha recalculado goleadores de verdad
                    // para esta jornada — antes de eso, sería una proyección que puede no coincidir
                    // nunca con el total oficial, así que preferimos no mostrar ningún número.
                    'puntos' => $goleadoresCalculadosOficial ? $puntosCalculados : null,
                    'calculado' => $goleadoresCalculadosOficial,
                    'oculto' => ! $goleadoresVisibles,
                ];
            });

            $puntosPartidos = (int) $partidosDeEstaJornada->sum('puntos');
            $puntosBonus = $bonusPlenoPorJornada->get($jornada, 0);
            $puntosGoleadores = (int) $goleadores->pluck('puntos')->filter(fn ($p) => ! is_null($p))->sum();

            return [
                'jornada' => $jornada,
                'bloqueada' => $bloqueada,
                'goleadores_calculados' => $goleadoresCalculadosOficial,
                'puntos_totales_jornada' => $puntosPartidos + $puntosBonus + $puntosGoleadores,
                'bonus_pleno' => $puntosBonus,
                'partidos' => $partidosDeEstaJornada,
                'goleadores' => $goleadores,
            ];
        });

        $puntosPronosticos = (int) $filasPorPartido->sum('puntos');
        $puntosBonusTotal = (int) $bonusPlenoPorJornada->sum();
        $puntosGoleadoresTotal = (int) $jornadas->sum(fn ($j) => collect($j['goleadores'])->pluck('puntos')->filter(fn ($p) => ! is_null($p))->sum());

        return response()->json([
            'data' => [
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre_visible ?? $usuario->name,
                    'avatar_url' => $usuario->avatar_url ? url($usuario->avatar_url) : null,
                ],
                'stats' => [
                    'total' => $filasPorPartido->count(),
                    'puntos_totales' => $puntosPronosticos + $puntosBonusTotal + $puntosGoleadoresTotal,
                    'aciertos' => $filasPorPartido->whereIn('tipo_evento', ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'])->count(),
                    'exactos' => $filasPorPartido->where('tipo_evento', 'AciertoExacto')->count(),
                ],
                'desglose' => [
                    'pronosticos' => $puntosPronosticos,
                    'bonus_pleno' => $puntosBonusTotal,
                    'goleadores' => $puntosGoleadoresTotal,
                ],
                'jornadas' => $jornadas,
            ],
        ]);
    }
}