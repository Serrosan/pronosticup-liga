<?php

use App\Services\TareasAdmin;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;

// Las tareas programadas salen del catálogo TareasAdmin::PROGRAMADAS — la misma
// lista que enseña /admin/tareas. Hoy: sincronizar partidos (cada 2 min),
// avisar pendientes (cada 30 min) y actualizar horarios próximos (08:00).
foreach (TareasAdmin::PROGRAMADAS as $tarea) {
    Schedule::command($tarea['comando'])->cron($tarea['cron']);
}

// Procesa los encargos pendientes de la cola y termina. Se encolan dos cosas:
// el scraper de LaLiga de UN partido, 10 minutos después de que ese partido
// pase a "Jugado" (ver SincronizarPartidosJob), y lo que se lance a mano desde
// /admin/tareas. Así no hace falta un proceso de cola aparte en producción.
//  - runInBackground: no retrasa al resto de tareas mientras descarga de LaLiga.
//  - withoutOverlapping(5): nunca dos a la vez; si un despliegue lo corta a
//    medias, el bloqueo caduca solo a los 5 minutos.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Apunta cuándo terminó cada tarea programada y cómo fue, para /admin/tareas.
// (La de la cola no está en el catálogo, así que no se apunta.)
Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $evento) {
    $codigo = $evento->task->exitCode ?? 0;

    TareasAdmin::registrarProgramada(
        (string) $evento->task->command,
        $codigo === 0,
        $evento->runtime,
        $codigo === 0 ? null : "Terminó con código de salida {$codigo}."
    );
});

Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $evento) {
    TareasAdmin::registrarProgramada((string) $evento->task->command, false, null, $evento->exception->getMessage());
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
