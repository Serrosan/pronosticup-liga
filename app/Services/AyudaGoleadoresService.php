<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use Illuminate\Support\Carbon;

/**
 * Lo que la pantalla de goleadores necesita para sugerir: los partidos de la
 * jornada (quién juega contra quién y si alguno está aplazado) y, por equipo,
 * cuántos goles marca y encaja en lo que va de temporada.
 *
 * Solo lee el calendario. No interviene en ningún cálculo de puntos ni dice
 * nada de lo que han elegido los demás.
 */
class AyudaGoleadoresService
{
    /**
     * @return array<int,array{id:int,estado:string,horario:?string,local:array,visitante:array}>
     */
    public function partidos(int $idTemporada, int $jornada): array
    {
        $balance = [];
        CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('estado', 'Jugado')
            ->whereNotNull('goles_casa')
            ->whereNotNull('goles_fuera')
            ->get(['id_equipo_local', 'id_equipo_visitante', 'goles_casa', 'goles_fuera'])
            ->each(function ($p) use (&$balance) {
                foreach ([
                    [$p->id_equipo_local, $p->goles_casa, $p->goles_fuera],
                    [$p->id_equipo_visitante, $p->goles_fuera, $p->goles_casa],
                ] as [$idEquipo, $marcados, $encajados]) {
                    $balance[$idEquipo] ??= ['jugados' => 0, 'marcados' => 0, 'encajados' => 0];
                    $balance[$idEquipo]['jugados']++;
                    $balance[$idEquipo]['marcados'] += (int) $marcados;
                    $balance[$idEquipo]['encajados'] += (int) $encajados;
                }
            });

        return CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
            ->where('id_temporada', $idTemporada)
            ->where('jornada', $jornada)
            ->orderBy('horario_estimado')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'estado' => $p->estado,
                'horario' => $p->horario_estimado ? Carbon::parse($p->horario_estimado)->toIso8601String() : null,
                'local' => $this->equipo($p->equipoLocal, $balance),
                'visitante' => $this->equipo($p->equipoVisitante, $balance),
            ])
            ->values()
            ->all();
    }

    private function equipo(?Equipo $equipo, array $balance): array
    {
        $datos = $balance[$equipo?->id] ?? ['jugados' => 0, 'marcados' => 0, 'encajados' => 0];

        return [
            'id' => $equipo?->id,
            'nombre' => $equipo?->nombre_corto ?? $equipo?->nombre ?? '¿?',
            'escudo_url' => $equipo?->escudo_url,
        ] + $datos;
    }
}
