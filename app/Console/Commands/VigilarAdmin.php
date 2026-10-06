<?php

namespace App\Console\Commands;

use App\Services\VigilanciaAdminService;
use Illuminate\Console\Command;

class VigilarAdmin extends Command
{
    protected $signature = 'admin:vigilar';

    protected $description = 'Avisa al admin por la campana: jornada lista para cerrar, tarea fallida o error nuevo';

    public function handle(VigilanciaAdminService $vigilancia): int
    {
        $enviados = $vigilancia->revisar();

        $this->info("Avisos enviados — jornadas listas: {$enviados['jornadas']}, tareas fallidas: {$enviados['tareas']}, errores nuevos: {$enviados['errores']}.");

        return self::SUCCESS;
    }
}
