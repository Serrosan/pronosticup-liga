<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MensajeRespondido extends Notification
{
    use Queueable;

    public function __construct(
        public string $nombreQuienResponde,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'chat_respuesta',
            'titulo' => '↩ Te han respondido',
            'mensaje' => "{$this->nombreQuienResponde} respondió a tu mensaje en el chat.",
            'importante' => false,
        ];
    }
}