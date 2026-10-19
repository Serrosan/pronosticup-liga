<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un mensaje que escribe el admin para toda la liga (o todos). Va a la campana,
 * sale como aviso importante al entrar en la app y llega al móvil
 * (categoría "📰 Avisos del admin").
 */
class AvisoDelAdmin extends Notification
{
    use Queueable;

    public function __construct(
        public string $titulo,
        public string $mensaje,
        public ?string $url = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'aviso_admin',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'importante' => true,
        ];
    }
}
