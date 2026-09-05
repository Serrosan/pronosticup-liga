<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SincronizacionFallida extends Notification
{
    use Queueable;

    public function __construct(private string $motivo) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'titulo' => '⚠️ Falló la sincronización de partidos',
            'mensaje' => 'football-data.org no respondió correctamente: '.substr($this->motivo, 0, 150),
        ];
    }
}