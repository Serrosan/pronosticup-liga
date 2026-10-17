<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Genera las claves VAPID de los avisos al móvil. Se hace UNA vez por entorno:
 * si se cambian, todos los dispositivos tienen que volver a activar los avisos.
 */
class GenerarClavesPush extends Command
{
    protected $signature = 'push:claves {--solo-valores : Solo imprime las dos líneas, sin explicaciones}';

    protected $description = 'Genera las claves VAPID para los avisos al móvil';

    public function handle(): int
    {
        $claves = VAPID::createVapidKeys();

        if (! $this->option('solo-valores')) {
            if (filled(config('services.webpush.public_key'))) {
                $this->warn('Ya hay claves configuradas. Si las cambias, todos tendrán que volver a activar los avisos.');
            }
            $this->line('Añade estas dos líneas al .env (y, en producción, a las variables de entorno de Coolify):');
            $this->newLine();
        }

        $this->line('VAPID_PUBLIC_KEY='.$claves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$claves['privateKey']);

        return self::SUCCESS;
    }
}
