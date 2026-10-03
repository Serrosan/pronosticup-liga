<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CartasSinJugar extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $idLiga,
        public int $cantidad,
        public string $nombreLiga,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $cartas = $this->cantidad === 1 ? '1 carta de Jugadas sin usar' : "{$this->cantidad} cartas de Jugadas sin usar";

        return [
            'tipo' => 'cartas_sin_jugar',
            'titulo' => '🃏 Tienes cartas sin jugar',
            'mensaje' => "La jornada {$this->jornada} empieza en pocas horas y tienes {$cartas} en {$this->nombreLiga}. Juégalas antes de que empiece, y acuérdate de pronosticar el partido si eliges uno.",
            'importante' => false,
            // Sirven para no avisar dos veces por lo mismo
            'jornada' => $this->jornada,
            'id_liga' => $this->idLiga,
        ];
    }
}