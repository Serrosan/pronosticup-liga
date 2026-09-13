<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MensajeReaccionado extends Notification
{
    use Queueable;

    public function __construct(
        public string $nombreQuienReacciona,
        public string $emoji,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'chat_reaccion',
            'titulo' => '😄 Nueva reacción',
            'mensaje' => "{$this->nombreQuienReacciona} reaccionó con {$this->emoji} a tu mensaje en el chat.",
            'importante' => false,
        ];
    }
}