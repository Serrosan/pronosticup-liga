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
        public string $nombreLiga,
    ) {}

    public function via($notifiable): array
    {
        return $notifiable->recibir_email_puntos ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'jornada_cerrada',
            'titulo' => "Jornada {$this->jornada} cerrada",
            'mensaje' => "Has conseguido {$this->puntos} puntos en {$this->nombreLiga}".($this->posicion ? " · vas #{$this->posicion}" : ''),
            'jornada' => $this->jornada,
            'importante' => true,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("📊 Resultados de la Jornada {$this->jornada} — {$this->nombreLiga}")
            ->greeting('¡Ya está todo resuelto!')
            ->line("Se ha cerrado la **Jornada {$this->jornada}** en tu liga **{$this->nombreLiga}**.")
            ->line("Has conseguido **{$this->puntos} puntos** con tus pronósticos de esta jornada.")
            ->when($this->posicion, fn ($mail) => $mail->line("Ahora mismo vas en la posición **#{$this->posicion}** de esa liga."))
            ->action('Ver clasificación', url('/clasificacion'))
            ->line('Sigue así para la próxima jornada — cada pronóstico cuenta.')
            ->salutation('¡Nos vemos en la próxima jornada! ⚽');
    }
}