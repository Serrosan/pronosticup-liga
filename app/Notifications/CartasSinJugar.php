<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Antes de que empiece la jornada: qué cartas puedes jugar todavía, con los
 * límites del juego (2 Jugadas por jornada, 1 Falta). Jugadas y Faltas por
 * separado, porque tienen plazos distintos.
 */
class CartasSinJugar extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $idLiga,
        public string $nombreLiga,
        public int $jugadasPosibles,
        public int $jugadasEnMano,
        public bool $faltaPosible,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $partes = [];

        if ($this->jugadasPosibles > 0) {
            $queda = $this->jugadasPosibles === 1 ? 'Te queda 1 Jugada' : "Te quedan {$this->jugadasPosibles} Jugadas";
            $mano = $this->jugadasEnMano === 1 ? '1 en la mano' : "{$this->jugadasEnMano} en la mano";
            $partes[] = "{$queda} por jugar (tienes {$mano}): hazlo antes de que empiece la jornada {$this->jornada}, y pronostica el partido si eliges uno.";
        }

        if ($this->faltaPosible) {
            $siguiente = $this->jornada + 1;
            $partes[] = $partes
                ? "Y tu Falta de esta semana: afectará a la jornada {$siguiente}."
                : "Te queda tu Falta de esta semana por jugar: afectará a la jornada {$siguiente}.";
        }

        return [
            'tipo' => 'cartas_sin_jugar',
            'titulo' => '🃏 Aún puedes jugar cartas',
            'mensaje' => "En {$this->nombreLiga}: ".implode(' ', $partes),
            'importante' => false,
            // Sirven para no avisar dos veces por lo mismo
            'jornada' => $this->jornada,
            'id_liga' => $this->idLiga,
        ];
    }
}
