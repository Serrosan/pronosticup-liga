<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('liga:sincronizar-partidos')->everyTwoMinutes();
Schedule::command('liga:avisar-pendientes')->everyThirtyMinutes();
Schedule::command('liga:actualizar-horarios-proximos')->dailyAt('08:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
