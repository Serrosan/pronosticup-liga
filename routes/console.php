<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('liga:sincronizar-partidos')->everyFiveMinutes();
Schedule::command('liga:avisar-pendientes')->everyThirtyMinutes();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
