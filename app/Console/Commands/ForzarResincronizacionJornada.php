<?php

namespace App\Console\Commands;

use App\Models\Arbitro;
use App\Models\CalendarioPartido;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class ForzarResincronizacionJornada extends Command
{
    protected $signature = 'liga:forzar-resincronizacion {jornada}';
    protected $description = 'Fuerza la comprobación de una jornada contra la API, sin importar el estado actual en BD (arregla datos de prueba que dejaron marcadores falsos, o rellena árbitros de jornadas ya jugadas)';

    public function handle(FootballDataService $servicio)
    {
        $jornada = (int) $this->argument('jornada');

        $this->info("Forzando resincronización de la jornada {$jornada}...");

        $partidosApi = $servicio->obtenerJornada($jornada);
        $actualizados = 0;
        $sinCambios = 0;

        foreach ($partidosApi as $partidoApi) {
            $partido = CalendarioPartido::where('id_partido_api', $partidoApi['id'])->first();

            if (! $partido) {
                $this->warn("  Sin vincular en BD: {$partidoApi['homeTeam']['name']} vs {$partidoApi['awayTeam']['name']}");
                continue;
            }

            $huboCambio = false;
            $datosArbitro = [];

            $arbitroApi = collect($partidoApi['referees'] ?? [])->firstWhere('type', 'REFEREE');
            if ($arbitroApi && ! empty($arbitroApi['name'])) {
                $idArbitroNuevo = Arbitro::resolverPorNombre($arbitroApi['name']);
                if ($partido->id_arbitro !== $idArbitroNuevo) {
                    $datosArbitro['id_arbitro'] = $idArbitroNuevo;
                    $this->line("  Árbitro actualizado: {$partidoApi['homeTeam']['name']} vs {$partidoApi['awayTeam']['name']} → {$arbitroApi['name']}");
                    $huboCambio = true;
                }
            }

            $estadoApi = $partidoApi['status'];

            if ($estadoApi === 'FINISHED') {
                $golesCasaReal = $partidoApi['score']['fullTime']['home'];
                $golesFueraReal = $partidoApi['score']['fullTime']['away'];

                if ($partido->goles_casa !== $golesCasaReal || $partido->goles_fuera !== $golesFueraReal || $partido->estado !== 'Jugado') {
                    $this->line("  CORRIGIENDO: {$partidoApi['homeTeam']['name']} {$partido->goles_casa}-{$partido->goles_fuera} → {$golesCasaReal}-{$golesFueraReal} {$partidoApi['awayTeam']['name']}");

                    $partido->update(array_merge($datosArbitro, [
                        'estado' => 'Jugado',
                        'goles_casa' => $golesCasaReal,
                        'goles_fuera' => $golesFueraReal,
                    ]));
                    $huboCambio = true;
                } elseif (! empty($datosArbitro)) {
                    $partido->update($datosArbitro);
                }
            } elseif (in_array($estadoApi, ['IN_PLAY', 'PAUSED'])) {
                $partido->update(array_merge($datosArbitro, [
                    'estado' => 'En juego',
                    'goles_casa' => $partidoApi['score']['fullTime']['home'] ?? $partido->goles_casa,
                    'goles_fuera' => $partidoApi['score']['fullTime']['away'] ?? $partido->goles_fuera,
                ]));
                $huboCambio = true;
            } elseif (in_array($estadoApi, ['SCHEDULED', 'TIMED'])) {
                $horarioNuevo = \Carbon\Carbon::parse($partidoApi['utcDate'])->setTimezone(config('app.timezone'));

                if ($partido->estado !== 'Programado' || ! $partido->horario_estimado?->equalTo($horarioNuevo)) {
                    $this->line("  CORRIGIENDO horario: {$partidoApi['homeTeam']['name']} vs {$partidoApi['awayTeam']['name']} → {$horarioNuevo->format('d/m/Y H:i')}");
                    $partido->update(array_merge($datosArbitro, [
                        'estado' => 'Programado',
                        'goles_casa' => null,
                        'goles_fuera' => null,
                        'horario_estimado' => $horarioNuevo,
                    ]));
                    $huboCambio = true;
                } elseif (! empty($datosArbitro)) {
                    $partido->update($datosArbitro);
                }
            }

            if ($huboCambio) {
                $actualizados++;
            } else {
                $sinCambios++;
            }
        }

        $this->newLine();
        $this->info("Listo. {$actualizados} partido(s) corregidos, {$sinCambios} ya estaban bien.");
    }
}