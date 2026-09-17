<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\User;
use Illuminate\Http\Request;

class EstadisticasLigaController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $partidosJugados = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('estado', 'Jugado')
            ->get(['id', 'goles_casa', 'goles_fuera', 'jornada']);

        $golesTotales = $partidosJugados->sum(fn ($p) => $p->goles_casa + $p->goles_fuera);

        $resultados = $partidosJugados
            ->map(fn ($p) => "{$p->goles_casa}-{$p->goles_fuera}")
            ->countBy()
            ->sortDesc();

        $resultadoTop = $resultados->keys()->first();
        $vecesResultadoTop = $resultados->first();

        // --- Equipo que más ha costado acertar a los miembros de esta liga ---
        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->whereNotNull('id_partido')
            ->whereIn('tipo_evento', ['Fallo', 'Acierto1x2', 'AciertoDiferencia', 'AciertoExacto'])
            ->with('partido')
            ->get();

        $porEquipo = [];
        foreach ($eventos as $evento) {
            $partido = $evento->partido;
            if (! $partido) {
                continue;
            }
            foreach ([$partido->id_equipo_local, $partido->id_equipo_visitante] as $idEquipo) {
                $porEquipo[$idEquipo]['total'] = ($porEquipo[$idEquipo]['total'] ?? 0) + 1;
                if ($evento->tipo_evento === 'Fallo') {
                    $porEquipo[$idEquipo]['fallos'] = ($porEquipo[$idEquipo]['fallos'] ?? 0) + 1;
                }
            }
        }

        $idEquipoMasDificil = null;
        $mejorRatio = 0;

        foreach ($porEquipo as $idEquipo => $datos) {
            if (($datos['total'] ?? 0) < 3) {
                continue;
            }
            $ratio = ($datos['fallos'] ?? 0) / $datos['total'];
            if ($ratio > $mejorRatio) {
                $mejorRatio = $ratio;
                $idEquipoMasDificil = $idEquipo;
            }
        }

        $equipoMasDificil = $idEquipoMasDificil ? Equipo::find($idEquipoMasDificil) : null;

        // --- Jugador más elegido como goleador por los miembros de esta liga ---
        $jugadorMasElegido = GoleadorJornada::where('id_liga', $liga->id)
            ->selectRaw('id_jugador, COUNT(*) as veces')
            ->groupBy('id_jugador')
            ->orderByDesc('veces')
            ->with('jugador')
            ->first();

        // --- Racha más larga de toda la historia de la liga (cualquier usuario, en cualquier momento) ---
        $eventosPorUsuario = $eventos->groupBy('id_usuario');
        $mejorRachaGlobal = 0;
        $idUsuarioMejorRacha = null;

        foreach ($eventosPorUsuario as $idUsuario => $eventosDeEsteUsuario) {
            $ordenados = $eventosDeEsteUsuario
                ->filter(fn ($e) => $e->partido)
                ->sortBy(fn ($e) => $e->partido->horario_estimado);

            $rachaActual = 0;
            $mejorRachaUsuario = 0;

            foreach ($ordenados as $evento) {
                if ($evento->tipo_evento === 'Fallo') {
                    $rachaActual = 0;
                } else {
                    $rachaActual++;
                    $mejorRachaUsuario = max($mejorRachaUsuario, $rachaActual);
                }
            }

            if ($mejorRachaUsuario > $mejorRachaGlobal) {
                $mejorRachaGlobal = $mejorRachaUsuario;
                $idUsuarioMejorRacha = $idUsuario;
            }
        }

        $usuarioMejorRacha = $idUsuarioMejorRacha ? User::find($idUsuarioMejorRacha) : null;

        // --- Jornada con más goles marcados en LaLiga esta temporada ---
        $golesPorJornada = $partidosJugados
            ->groupBy('jornada')
            ->map(fn ($grupo) => $grupo->sum(fn ($p) => $p->goles_casa + $p->goles_fuera));

        $jornadaMasGoles = $golesPorJornada->sortDesc()->keys()->first();
        $golesEnEsaJornada = $golesPorJornada->max();

        // --- Mayor sorpresa: el partido donde MENOS gente de la liga acertó el signo ---
        $eventosPorPartido = $eventos->groupBy('id_partido');
        $idPartidoSorpresa = null;
        $menorPorcentajeAcierto = 101;
        $participacionMinima = 2; // ignoramos partidos con muy poca gente pronosticando, para no distorsionar

        foreach ($eventosPorPartido as $idPartido => $eventosDeEsePartido) {
            $total = $eventosDeEsePartido->count();
            if ($total < $participacionMinima) {
                continue;
            }
            $aciertos = $eventosDeEsePartido->whereIn('tipo_evento', ['Acierto1x2', 'AciertoDiferencia', 'AciertoExacto'])->count();
            $porcentaje = ($aciertos / $total) * 100;

            if ($porcentaje < $menorPorcentajeAcierto) {
                $menorPorcentajeAcierto = $porcentaje;
                $idPartidoSorpresa = $idPartido;
            }
        }

        $partidoSorpresa = $idPartidoSorpresa
            ? CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->find($idPartidoSorpresa)
            : null;

        // --- Usuario con más resultados exactos de toda la historia de la liga ---
        $reyDeLosExactos = EventoPuntos::where('id_liga', $liga->id)
            ->where('tipo_evento', 'AciertoExacto')
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->first();

        $usuarioReyExactos = $reyDeLosExactos ? User::find($reyDeLosExactos->id_usuario) : null;

        return response()->json([
            'data' => [
                'goles_totales' => (int) $golesTotales,
                'resultado_mas_repetido' => $resultadoTop,
                'veces_resultado_mas_repetido' => $vecesResultadoTop ? (int) $vecesResultadoTop : null,
                'equipo_mas_dificil' => $equipoMasDificil ? [
                    'nombre' => $equipoMasDificil->nombre_corto ?? $equipoMasDificil->nombre,
                    'escudo_url' => $equipoMasDificil->escudo_url,
                    'porcentaje_fallo' => round($mejorRatio * 100),
                ] : null,
                'jugador_mas_elegido' => $jugadorMasElegido && $jugadorMasElegido->jugador ? [
                    'nombre' => $jugadorMasElegido->jugador->nombre_camiseta ?? trim("{$jugadorMasElegido->jugador->nombre} {$jugadorMasElegido->jugador->apellidos}"),
                    'foto_url' => $jugadorMasElegido->jugador->foto_url,
                    'veces' => (int) $jugadorMasElegido->veces,
                ] : null,
                'mejor_racha_historica' => $usuarioMejorRacha ? [
                    'nombre' => $usuarioMejorRacha->nombre_visible ?? $usuarioMejorRacha->name,
                    'avatar_url' => $usuarioMejorRacha->avatar_url ? url($usuarioMejorRacha->avatar_url) : null,
                    'racha' => $mejorRachaGlobal,
                ] : null,
                'jornada_mas_goleadora' => $jornadaMasGoles ? [
                    'jornada' => $jornadaMasGoles,
                    'goles' => (int) $golesEnEsaJornada,
                ] : null,
                'partido_sorpresa' => $partidoSorpresa ? [
                    'equipo_local' => $partidoSorpresa->equipoLocal->nombre_corto ?? $partidoSorpresa->equipoLocal->nombre,
                    'equipo_visitante' => $partidoSorpresa->equipoVisitante->nombre_corto ?? $partidoSorpresa->equipoVisitante->nombre,
                    'escudo_local' => $partidoSorpresa->equipoLocal->escudo_url,
                    'escudo_visitante' => $partidoSorpresa->equipoVisitante->escudo_url,
                    'goles_casa' => $partidoSorpresa->goles_casa,
                    'goles_fuera' => $partidoSorpresa->goles_fuera,
                    'porcentaje_acierto' => round($menorPorcentajeAcierto),
                ] : null,
                'rey_de_los_exactos' => $usuarioReyExactos ? [
                    'nombre' => $usuarioReyExactos->nombre_visible ?? $usuarioReyExactos->name,
                    'avatar_url' => $usuarioReyExactos->avatar_url ? url($usuarioReyExactos->avatar_url) : null,
                    'total' => (int) $reyDeLosExactos->total,
                ] : null,
            ],
        ]);
    }
}