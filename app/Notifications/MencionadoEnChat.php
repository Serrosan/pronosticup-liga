<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MencionadoEnChat extends Notification
{
    use Queueable;

    public function __construct(public string $nombreQuienMenciona) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'chat_mencion',
            'titulo' => '💬 Te han mencionado',
            'mensaje' => "{$this->nombreQuienMenciona} te ha mencionado en el chat.",
            'importante' => false,
        ];
    }
}