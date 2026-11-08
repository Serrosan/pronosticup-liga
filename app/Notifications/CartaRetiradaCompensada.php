<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CartaRetiradaCompensada extends Notification
{
    use Queueable;

    public function __construct(public string $nombreCarta, public int $sobres) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $cuantas = $this->sobres === 1 ? 'un sobre nuevo' : "{$this->sobres} sobres nuevos, uno por cada carta retirada";

        return [
            'tipo' => 'carta_manual',
            'titulo' => '🃏 Una carta se retira: te llega el cambio',
            'mensaje' => "«{$this->nombreCarta}» se ha retirado del juego. A cambio tienes {$cuantas}: ábrelos en Mis Cartas.",
            'importante' => false,
        ];
    }
}
