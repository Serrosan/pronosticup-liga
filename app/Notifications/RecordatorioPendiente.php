<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecordatorioPendiente extends Notification
{
    use Queueable;

    public function __construct(
        public int $jornada,
        public string $nombreLiga,
        public string $tipo,
        public int $ventanaHoras,
    ) {}

    public function via($notifiable): array
    {
        return $notifiable->recibir_email_recordatorios ? ['database', 'mail'] : ['database'];
    }

    private function textoTipo(): string
    {
        return $this->tipo === 'goleadores' ? 'tus 5 goleadores' : 'tus pronósticos';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'tipo' => 'recordatorio_pendiente',
            'titulo' => "⏰ Quedan {$this->ventanaHoras}h para la Jornada {$this->jornada}",
            'mensaje' => "Aún te faltan {$this->textoTipo()} en {$this->nombreLiga}.",
            'jornada' => $this->jornada,
            'importante' => $this->ventanaHoras <= 12,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $rutaAccion = $this->tipo === 'goleadores' ? "/jornadas/{$this->jornada}/goleadores" : "/jornadas/{$this->jornada}";
        $etiquetaAccion = $this->tipo === 'goleadores' ? 'Elegir goleadores' : 'Rellenar pronósticos';

        return (new MailMessage)
            ->subject("⏰ Quedan {$this->ventanaHoras}h para la Jornada {$this->jornada} — {$this->nombreLiga}")
            ->greeting('¡No te quedes fuera de esta jornada!')
            ->line("Quedan aproximadamente **{$this->ventanaHoras} horas** para que empiece la Jornada {$this->jornada} en **{$this->nombreLiga}**.")
            ->line("Todavía te faltan {$this->textoTipo()} de esta jornada.")
            ->action($etiquetaAccion, url($rutaAccion))
            ->salutation('¡Nos vemos en la jornada! ⚽');
    }
}