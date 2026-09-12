<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use Illuminate\Console\Command;

class ActualizarHorariosProximos extends Command
{
    protected $signature = 'liga:actualizar-horarios-proximos {--jornadas-vista=3}';
    protected $description = 'Revisa una vez al día las próximas jornadas por si la API ya confirmó horarios oficiales, sin esperar a que el partido esté a punto de empezar';

    public function handle()
    {
        $jornadasVista = (int) $this->option('jornadas-vista');

        $proximaJornada = CalendarioPartido::whereIn('estado', ['Programado', 'Aplazado'])
            ->orderBy('jornada')
            ->value('jornada');

        if (! $proximaJornada) {
            $this->info('No hay jornadas pendientes que revisar.');
            return;
        }

        for ($jornada = $proximaJornada; $jornada < $proximaJornada + $jornadasVista; $jornada++) {
            $this->info("--- Revisando jornada {$jornada} ---");
            $this->call('liga:forzar-resincronizacion', ['jornada' => $jornada]);
        }
    }
}