<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JornadaProximaACerrar extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $partidosPendientes,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'jornada_proxima_a_cerrar',
            'titulo' => "¡Quedan pocas horas! Jornada {$this->jornada}",
            'mensaje' => "Tienes {$this->partidosPendientes} partido(s) sin pronosticar en la Jornada {$this->jornada}.",
            'jornada' => $this->jornada,
            'importante' => true,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚽ Quedan pocas horas — Jornada {$this->jornada}")
            ->greeting('¡Eh, que se te olvida algo!')
            ->line("Tienes {$this->partidosPendientes} partido(s) sin pronosticar en la Jornada {$this->jornada}.")
            ->action('Pronosticar ahora', url("/jornadas/{$this->jornada}"))
            ->line('No querrás quedarte sin puntos por despiste.');
    }
}