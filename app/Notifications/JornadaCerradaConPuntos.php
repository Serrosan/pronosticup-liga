<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JornadaCerradaConPuntos extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public int $puntos,
        public ?int $posicion,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'jornada_cerrada',
            'titulo' => "Jornada {$this->jornada} cerrada",
            'mensaje' => "Has conseguido {$this->puntos} puntos".($this->posicion ? " · vas #{$this->posicion} en tu liga" : ''),
            'jornada' => $this->jornada,
            'importante' => true,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("📊 Resultados de la Jornada {$this->jornada}")
            ->greeting('¡Ya está todo resuelto!')
            ->line("Has conseguido **{$this->puntos} puntos** en la Jornada {$this->jornada}.")
            ->when($this->posicion, fn ($mail) => $mail->line("Vas en la posición #{$this->posicion} de tu liga."))
            ->action('Ver clasificación', url('/clasificacion'));
    }
}