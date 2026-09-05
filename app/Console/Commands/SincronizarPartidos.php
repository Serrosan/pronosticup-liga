<?php

namespace App\Console\Commands;

use App\Jobs\Partidos\SincronizarPartidosJob;
use App\Models\User;
use App\Notifications\SincronizacionFallida;
use Illuminate\Console\Command;

class SincronizarPartidos extends Command
{
    protected $signature = 'liga:sincronizar-partidos';
    protected $description = 'Sincroniza el estado y resultado de los partidos pendientes con football-data.org';

    public function handle()
    {
        try {
            SincronizarPartidosJob::dispatchSync();
            $this->info('Sincronización completada.');
        } catch (\Throwable $e) {
            $this->error('Fallo al sincronizar: '.$e->getMessage());

            User::where('es_superadmin', true)->get()->each(function ($admin) use ($e) {
                $admin->notify(new SincronizacionFallida($e->getMessage()));
            });
        }
    }
}