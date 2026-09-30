<?php

namespace App\Console\Commands;

use App\Jobs\Partidos\SincronizarPartidoLaligaJob;
use Illuminate\Console\Command;

class SincronizarPartidoLaliga extends Command
{
    protected $signature = 'liga:sincronizar-partido-laliga {id_partido : El id de calendariopartidos}';

    protected $description = 'Trae alineaciones/estadísticas/eventos de UN partido concreto desde LaLiga.com — pensado para uso manual o para engancharse al momento en que un partido pasa a Jugado';

    public function handle(): int
    {
        $idPartido = (int) $this->argument('id_partido');

        try {
            SincronizarPartidoLaligaJob::dispatchSync($idPartido);
            $this->info("Sincronización completada para el partido {$idPartido}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Fallo: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}