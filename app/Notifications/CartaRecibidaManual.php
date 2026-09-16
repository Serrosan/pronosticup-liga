<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CartaRecibidaManual extends Notification
{
    use Queueable;

    public function __construct(public string $nombreCarta) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'carta_manual',
            'titulo' => '🃏 Has recibido una carta',
            'mensaje' => "El admin te ha dado la carta: {$this->nombreCarta}.",
            'importante' => false,
        ];
    }
}