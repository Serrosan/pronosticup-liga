<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
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
            ->get(['goles_casa', 'goles_fuera']);

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
                continue; // muestra mínima para que un solo partido no distorsione el dato
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
            ],
        ]);
    }
}
