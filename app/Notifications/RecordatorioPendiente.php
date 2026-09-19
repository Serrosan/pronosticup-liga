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
        $etiquetaAccion = $this->tipo === 'goleadores' ? 'elegir tus goleadores' : 'rellenar tus pronósticos';
        $urlCompleta = url($rutaAccion);

        return (new MailMessage)
            ->subject("Pronósticos pendientes — Jornada {$this->jornada} de {$this->nombreLiga}")
            ->greeting('Recordatorio de tu liga')
            ->line("Quedan {$this->ventanaHoras} horas para que empiece la Jornada {$this->jornada} en {$this->nombreLiga}.")
            ->line("Todavía te faltan {$this->textoTipo()} de esta jornada.")
            ->line("Puedes {$etiquetaAccion} aquí: {$urlCompleta}")
            ->salutation('Un saludo, PronostiCup Liga');
    }
}