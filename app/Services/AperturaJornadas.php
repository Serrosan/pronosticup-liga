<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\Liga;
use Illuminate\Support\Carbon;

/**
 * En las ligas con cartas, una jornada solo se puede pronosticar (y elegir
 * goleadores) cuando ha terminado la anterior. Es el mismo momento en que dejan
 * de poder jugarse Faltas contra ella (las Faltas caen en "la jornada siguiente
 * a la que está en juego"), así que quien la rellena ya ve todas las que le han
 * jugado y nunca le llega una después de haberla rellenado.
 *
 * Las ligas sin cartas no cambian: se puede pronosticar con la antelación que se quiera.
 */
class AperturaJornadas
{
    /** null si la jornada está abierta; si no, el texto que explica cuándo se abre. */
    public function motivoCerrada(Liga $liga, int $jornada): ?string
    {
        if ($liga->tipo !== 'ConExtras') {
            return null;
        }

        $enJuego = CalendarioPartido::proximaJornadaPorJugar((int) $liga->id_temporada);

        if ($enJuego === null || $jornada <= $enJuego) {
            return null;
        }

        $anterior = $jornada - 1;
        $ultimo = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $anterior)
            ->whereNotNull('horario_estimado')
            ->get()
            ->reject(fn ($p) => $p->estaDescolgadoDeSuJornada())
            ->max('horario_estimado');

        $cuando = $ultimo
            ? ' (último partido: '.Carbon::parse($ultimo)->timezone(config('app.timezone'))->format('d/m H:i').')'
            : '';

        return "La jornada {$jornada} se abre cuando termine la jornada {$anterior}{$cuando}. Así, cuando la rellenes, ya sabrás qué Faltas te han jugado.";
    }
}
