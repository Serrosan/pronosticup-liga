<?php

namespace App\Console\Commands;

use App\Jobs\Partidos\SincronizarPartidosJob;
use Illuminate\Console\Command;

class SincronizarPartidos extends Command
{
    protected $signature = 'liga:sincronizar-partidos';
    protected $description = 'Sincroniza el estado y resultado de los partidos pendientes con football-data.org';

    public function handle()
    {
        SincronizarPartidosJob::dispatchSync();
        $this->info('Sincronización completada.');
    }
}