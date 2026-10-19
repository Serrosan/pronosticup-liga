<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Ligas con cartas: ya ha terminado la jornada anterior y se puede pronosticar esta. */
class JornadaAbierta extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $idLiga,
        public string $nombreLiga,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'jornada_abierta',
            'titulo' => "🔓 Ya puedes pronosticar la jornada {$this->jornada}",
            'mensaje' => "Ha terminado la jornada ".($this->jornada - 1)." en {$this->nombreLiga}: rellena tus pronósticos y tus goleadores. Las Faltas que te hayan jugado ya las ves.",
            'importante' => false,
            // Sirven para no avisar dos veces por lo mismo
            'jornada' => $this->jornada,
            'id_liga' => $this->idLiga,
        ];
    }
}
