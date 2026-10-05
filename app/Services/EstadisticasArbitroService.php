<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\EventoPartido;

/**
 * Las tarjetas del árbitro de un partido en lo que va de temporada, sacadas
 * de los eventos que ya importa el scraper. Solo lee.
 *
 * Solo cuentan los partidos que tienen eventos importados: uno jugado pero sin
 * importar saldría como "cero tarjetas" y falsearía la media.
 */
class EstadisticasArbitroService
{
    /**
     * @return array{nombre:string,partidos:int,amarillas:int,rojas:int,partidos_con_roja:int}|null
     *         null si el partido no tiene árbitro asignado.
     */
    public function delPartido(CalendarioPartido $partido): ?array
    {
        $arbitro = $partido->arbitro;

        if (! $arbitro) {
            return null;
        }

        $idsPitados = CalendarioPartido::where('id_temporada', $partido->id_temporada)
            ->where('id_arbitro', $arbitro->id)
            ->where('estado', 'Jugado')
            ->pluck('id');

        $eventos = EventoPartido::whereIn('id_partido', $idsPitados)->get(['id_partido', 'tipo_evento']);

        $rojas = $eventos->where('tipo_evento', 'tarjeta_roja');

        return [
            'nombre' => trim("{$arbitro->nombre} {$arbitro->apellidos}"),
            'partidos' => $eventos->pluck('id_partido')->unique()->count(),
            'amarillas' => $eventos->where('tipo_evento', 'tarjeta_amarilla')->count(),
            'rojas' => $rojas->count(),
            'partidos_con_roja' => $rojas->pluck('id_partido')->unique()->count(),
        ];
    }
}
