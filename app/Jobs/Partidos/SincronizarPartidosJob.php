<?php

namespace App\Jobs\Partidos;

use App\Models\CalendarioPartido;
use App\Services\FootballDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SincronizarPartidosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FootballDataService $servicio): void
    {
        $jornadasPendientes = CalendarioPartido::whereIn('estado', ['Programado', 'Aplazado'])
            ->whereNotNull('id_partido_api')
            ->where('horario_estimado', '<=', now())
            ->distinct()
            ->pluck('jornada');

        foreach ($jornadasPendientes as $jornada) {
            $partidosApi = $servicio->obtenerJornada($jornada);

            foreach ($partidosApi as $partidoApi) {
                $partido = CalendarioPartido::where('id_partido_api', $partidoApi['id'])->first();

                if (! $partido) {
                    continue;
                }

                $estadoApi = $partidoApi['status'];

                if ($estadoApi === 'FINISHED') {
                    $partido->update([
                        'estado' => 'Jugado',
                        'goles_casa' => $partidoApi['score']['fullTime']['home'],
                        'goles_fuera' => $partidoApi['score']['fullTime']['away'],
                    ]);
                } elseif (in_array($estadoApi, ['POSTPONED', 'CANCELLED', 'SUSPENDED'])) {
                    $partido->update(['estado' => 'Aplazado']);
                } elseif (in_array($estadoApi, ['SCHEDULED', 'TIMED'])) {
                    $partido->update([
                        'estado' => 'Programado',
                        'horario_estimado' => $partidoApi['utcDate'],
                    ]);
                }
            }
        }
    }
}