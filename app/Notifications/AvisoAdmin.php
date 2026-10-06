<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso de la vigilancia al admin (ver VigilanciaAdminService). Solo campana.
 *
 * El tipo decide a qué pantalla lleva al pulsarlo (CampanaNotificaciones.jsx):
 * admin_jornada_lista, admin_tarea_fallida o admin_error_nuevo.
 */
class AvisoAdmin extends Notification
{
    use Queueable;

    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $mensaje,
        public bool $importante = false,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'importante' => $this->importante,
        ];
    }
}
