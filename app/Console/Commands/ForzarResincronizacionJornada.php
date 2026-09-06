<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Services\FootballDataService;
use Illuminate\Console\Command;

class ForzarResincronizacionJornada extends Command
{
    protected $signature = 'liga:forzar-resincronizacion {jornada}';
    protected $description = 'Fuerza la comprobación de una jornada contra la API, sin importar el estado actual en BD (arregla datos de prueba que dejaron marcadores falsos)';

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

            $estadoApi = $partidoApi['status'];

            if ($estadoApi === 'FINISHED') {
                $golesCasaReal = $partidoApi['score']['fullTime']['home'];
                $golesFueraReal = $partidoApi['score']['fullTime']['away'];

                if ($partido->goles_casa !== $golesCasaReal || $partido->goles_fuera !== $golesFueraReal || $partido->estado !== 'Jugado') {
                    $this->line("  CORRIGIENDO: {$partidoApi['homeTeam']['name']} {$partido->goles_casa}-{$partido->goles_fuera} → {$golesCasaReal}-{$golesFueraReal} {$partidoApi['awayTeam']['name']}");

                    $partido->update([
                        'estado' => 'Jugado',
                        'goles_casa' => $golesCasaReal,
                        'goles_fuera' => $golesFueraReal,
                    ]);
                    $actualizados++;
                } else {
                    $sinCambios++;
                }
            } elseif (in_array($estadoApi, ['IN_PLAY', 'PAUSED'])) {
                $partido->update([
                    'estado' => 'En juego',
                    'goles_casa' => $partidoApi['score']['fullTime']['home'] ?? $partido->goles_casa,
                    'goles_fuera' => $partidoApi['score']['fullTime']['away'] ?? $partido->goles_fuera,
                ]);
                $actualizados++;
            } elseif (in_array($estadoApi, ['SCHEDULED', 'TIMED'])) {
                if ($partido->estado !== 'Programado') {
                    $this->line("  CORRIGIENDO estado a Programado: {$partidoApi['homeTeam']['name']} vs {$partidoApi['awayTeam']['name']}");
                    $partido->update(['estado' => 'Programado', 'goles_casa' => null, 'goles_fuera' => null]);
                    $actualizados++;
                } else {
                    $sinCambios++;
                }
            }
        }

        $this->newLine();
        $this->info("Listo. {$actualizados} partido(s) corregidos, {$sinCambios} ya estaban bien.");
    }
}