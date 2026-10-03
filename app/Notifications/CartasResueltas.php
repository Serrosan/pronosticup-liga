<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CartasResueltas extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $total,
        public int $cumplidas,
        public int $puntos,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $unica = $this->total === 1;
        $cartas = $unica ? 'se ha resuelto tu carta' : "se han resuelto tus {$this->total} cartas";

        if ($this->puntos > 0) {
            $resultado = $unica ? "sumó {$this->puntos} pt" : "sumaron {$this->puntos} pt en total";
        } else {
            $resultado = $unica ? 'no sumó puntos esta vez' : 'no sumaron puntos esta vez';
        }

        return [
            'tipo' => 'cartas_resueltas',
            'titulo' => '🃏 Cartas resueltas',
            'mensaje' => "Jornada {$this->jornada}: {$cartas} y {$resultado}. Míralo en el Historial de Mis Cartas.",
            'importante' => false,
        ];
    }
}