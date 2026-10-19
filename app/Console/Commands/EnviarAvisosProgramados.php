<?php

namespace App\Console\Commands;

use App\Services\AvisosDelAdminService;
use Illuminate\Console\Command;

/** Cada minuto: envía los avisos del admin programados cuya hora ya ha llegado. */
class EnviarAvisosProgramados extends Command
{
    protected $signature = 'avisos:enviar-programados';

    protected $description = 'Envía los avisos del admin programados cuya hora ya ha llegado';

    public function handle(AvisosDelAdminService $avisos): int
    {
        $enviados = $avisos->enviarPendientes();

        if ($enviados > 0) {
            $this->info("{$enviados} aviso(s) programado(s) enviado(s).");
        }

        return self::SUCCESS;
    }
}
