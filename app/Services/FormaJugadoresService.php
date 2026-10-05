<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\EventoPartido;

/**
 * "Cómo viene" cada jugador, para ayudar a elegir goleadores: goles de la
 * temporada y titularidades en los últimos partidos de su equipo.
 *
 * Solo lee datos que ya guarda el importador de LaLiga (alineaciones y
 * eventos). No interviene en ningún cálculo de puntos.
 */
class FormaJugadoresService
{
    /** Cuántos partidos recientes de cada equipo se miran. */
    public const PARTIDOS_RECIENTES = 5;

    /**
     * @return array<int,array{goles:int,goles_recientes:int,titular:int,convocado:int,de:?int}>
     *         por id de jugador; solo salen los que tienen algún dato.
     */
    public function deTemporada(int $idTemporada): array
    {
        $jugados = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('estado', 'Jugado')
            ->orderByDesc('horario_estimado')
            ->orderByDesc('id')
            ->get(['id', 'id_equipo_local', 'id_equipo_visitante']);

        if ($jugados->isEmpty()) {
            return [];
        }

        $idsJugados = $jugados->pluck('id');

        // Los últimos partidos de cada equipo que tienen la alineación importada.
        $conAlineacion = AlineacionJugador::whereIn('id_partido', $idsJugados)->distinct()->pluck('id_partido')->flip();
        $recientes = [];
        foreach ($jugados as $partido) {
            if (! isset($conAlineacion[$partido->id])) {
                continue;
            }
            foreach ([$partido->id_equipo_local, $partido->id_equipo_visitante] as $idEquipo) {
                if (count($recientes[$idEquipo] ?? []) < self::PARTIDOS_RECIENTES) {
                    $recientes[$idEquipo][$partido->id] = true;
                }
            }
        }

        $forma = [];
        $base = ['goles' => 0, 'goles_recientes' => 0, 'titular' => 0, 'convocado' => 0, 'de' => null];

        $idsRecientes = collect($recientes)->flatMap(fn ($partidos) => array_keys($partidos))->unique()->values();

        AlineacionJugador::whereIn('id_partido', $idsRecientes)
            ->whereNotNull('id_jugador')
            ->get(['id_partido', 'id_equipo', 'id_jugador', 'titular'])
            ->each(function ($fila) use (&$forma, $recientes, $base) {
                if (! isset($recientes[$fila->id_equipo][$fila->id_partido])) {
                    return; // reciente para el rival, pero no para su equipo
                }
                $forma[$fila->id_jugador] ??= $base;
                $forma[$fila->id_jugador]['convocado']++;
                $forma[$fila->id_jugador]['titular'] += $fila->titular ? 1 : 0;
                $forma[$fila->id_jugador]['de'] = count($recientes[$fila->id_equipo]);
            });

        // Solo 'gol': los goles en propia puerta tienen su propio tipo y no cuentan.
        EventoPartido::whereIn('id_partido', $idsJugados)
            ->where('tipo_evento', 'gol')
            ->whereNotNull('id_jugador')
            ->get(['id_partido', 'id_equipo', 'id_jugador'])
            ->each(function ($gol) use (&$forma, $recientes, $base) {
                $forma[$gol->id_jugador] ??= $base;
                $forma[$gol->id_jugador]['goles']++;
                if (isset($recientes[$gol->id_equipo][$gol->id_partido])) {
                    $forma[$gol->id_jugador]['goles_recientes']++;
                }
            });

        return $forma;
    }
}
