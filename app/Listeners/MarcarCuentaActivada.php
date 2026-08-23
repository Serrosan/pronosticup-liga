<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;

class MarcarCuentaActivada
{
    public function handle(Verified $event): void
    {
        $event->user->forceFill(['activado_en' => now()])->save();
    }
}