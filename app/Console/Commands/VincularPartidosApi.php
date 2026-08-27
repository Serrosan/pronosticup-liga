<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class VincularPartidosApi extends Command
{
    protected $signature = 'liga:vincular-partidos-api';
    protected $description = 'Vincula cada partido de la BD con su ID real en football-data.org';

    public function handle(FootballDataService $servicio)
    {
        $partidosApi = $servicio->obtenerTemporadaCompleta();
        $equiposPorIdApi = Equipo::whereNotNull('id_equipo_api')->get()->keyBy('id_equipo_api');

        $vinculados = 0;
        $sinEncontrar = 0;

        foreach ($partidosApi as $partidoApi) {
            $equipoLocal = $equiposPorIdApi->get($partidoApi['homeTeam']['id']);
            $equipoVisitante = $equiposPorIdApi->get($partidoApi['awayTeam']['id']);

            if (! $equipoLocal || ! $equipoVisitante) {
                $sinEncontrar++;
                continue;
            }

            $actualizado = CalendarioPartido::where('jornada', $partidoApi['matchday'])
                ->where('id_equipo_local', $equipoLocal->id)
                ->where('id_equipo_visitante', $equipoVisitante->id)
                ->update(['id_partido_api' => $partidoApi['id']]);

            $vinculados += $actualizado;
        }

        $this->info("Vinculados: {$vinculados}. Sin encontrar equipo: {$sinEncontrar}.");
    }
}