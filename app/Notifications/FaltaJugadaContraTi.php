<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FaltaJugadaContraTi extends Notification
{
    use Queueable;

    public function __construct(
        public string $nombreAtacante,
        public string $nombreCarta,
        public int $jornadaEfecto,
        public bool $conAudio = false,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'falta_recibida',
            'titulo' => '⚠️ Te han jugado una Falta',
            'mensaje' => "{$this->nombreAtacante} te ha jugado \"{$this->nombreCarta}\" — afecta a la Jornada {$this->jornadaEfecto}."
                .($this->conAudio ? ' 🎙️ Y te ha dejado un audio.' : ''),
            'importante' => true,
        ];
    }
}