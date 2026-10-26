<?php

namespace App\Console\Commands;

use App\Services\VigilanciaAdminService;
use Illuminate\Console\Command;

class VigilarAdmin extends Command
{
    protected $signature = 'admin:vigilar';

    protected $description = 'Avisa al admin por la campana: jornada lista para cerrar, tarea fallida, error nuevo o partido atascado';

    public function handle(VigilanciaAdminService $vigilancia): int
    {
        $enviados = $vigilancia->revisar();
        $atascados = $vigilancia->partidosAtascados();

        $this->info("Avisos enviados — jornadas listas: {$enviados['jornadas']}, tareas fallidas: {$enviados['tareas']}, errores nuevos: {$enviados['errores']}, partidos atascados: {$atascados}.");

        return self::SUCCESS;
    }
}
