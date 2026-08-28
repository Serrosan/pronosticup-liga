<?php

namespace App\Jobs\Partidos;

use App\Models\CalendarioPartido;
use App\Services\FootballDataService;
use Carbon\Carbon;
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
        $jornadasPendientes = CalendarioPartido::whereIn('estado', ['Programado', 'Aplazado', 'En juego'])
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
                } elseif (in_array($estadoApi, ['IN_PLAY', 'PAUSED'])) {
                    $partido->update([
                        'estado' => 'En juego',
                        'goles_casa' => $partidoApi['score']['fullTime']['home'] ?? $partido->goles_casa,
                        'goles_fuera' => $partidoApi['score']['fullTime']['away'] ?? $partido->goles_fuera,
                    ]);
                } elseif (in_array($estadoApi, ['POSTPONED', 'CANCELLED', 'SUSPENDED'])) {
                    $partido->update(['estado' => 'Aplazado']);
                } elseif (in_array($estadoApi, ['SCHEDULED', 'TIMED'])) {
                    $partido->update([
                        'estado' => 'Programado',
                        'horario_estimado' => Carbon::parse($partidoApi['utcDate'])->setTimezone(config('app.timezone')),
                    ]);
                }
            }
        }
    }
}