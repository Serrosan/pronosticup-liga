<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('liga:sincronizar-partidos')->everyTwoMinutes();
Schedule::command('liga:avisar-pendientes')->everyThirtyMinutes();
Schedule::command('liga:actualizar-horarios-proximos')->dailyAt('08:00');

// Procesa los encargos pendientes de la cola y termina. Hoy el único que se
// encola es el scraper de LaLiga de UN partido, 10 minutos después de que ese
// partido pase a "Jugado" (ver SincronizarPartidosJob). Así no hace falta un
// proceso de cola aparte en producción: lo recoge el mismo programador que ya
// lanza la sincronización de partidos.
//  - runInBackground: no retrasa al resto de tareas mientras descarga de LaLiga.
//  - withoutOverlapping(5): nunca dos a la vez; si un despliegue lo corta a
//    medias, el bloqueo caduca solo a los 5 minutos.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');