<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Jornada cerrada": sale una sola vez por jornada y liga, al calcular los
 * goleadores, cuando ya están TODOS los puntos (ver JornadaController).
 */
class JornadaCerradaConPuntos extends Notification
{
    use Queueable;

    /**
     * @param  array  $resumen  Desglose de ResumenPuntosJornadaService::paraUsuario().
     *                          Vacío = formato corto (solo total y posición).
     */
    public function __construct(
        public int $jornada,
        public int $puntos,
        public ?int $posicion,
        public string $nombreLiga,
        public array $resumen = [],
    ) {}

    public function via($notifiable): array
    {
        return $notifiable->recibir_email_puntos ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $mensaje = "Has sumado {$this->puntos} puntos en {$this->nombreLiga}";

        if ($this->resumen) {
            $r = $this->resumen;
            $partes = ["⚽ {$r['pronosticos']}"];
            if ($r['bonus_pleno'] > 0) {
                $partes[] = "🏆 +{$r['bonus_pleno']}";
            }
            if ($r['goleadores_elegidos'] > 0) {
                $partes[] = "🎯 {$r['goleadores_puntos']}";
            }
            if ($r['mostrar_cartas']) {
                $partes[] = '🃏 '.$this->conSigno($r['cartas']);
            }
            $mensaje .= ' ('.implode(' · ', $partes).')';
        }

        if ($this->posicion) {
            $mensaje .= " · vas #{$this->posicion}".$this->flechaCorta();
        }

        return [
            'tipo' => 'jornada_cerrada',
            'titulo' => "Jornada {$this->jornada} cerrada",
            'mensaje' => $mensaje,
            'jornada' => $this->jornada,
            'importante' => true,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("📊 Jornada {$this->jornada}: {$this->puntos} puntos — {$this->nombreLiga}")
            ->greeting("¡Jornada {$this->jornada} cerrada!")
            ->line("Ya están todos los puntos de la **Jornada {$this->jornada}** en tu liga **{$this->nombreLiga}**.")
            ->line("Tus puntos de esta jornada: **{$this->puntos}**");

        if ($this->resumen) {
            $mail->line($this->desglose($this->resumen));
        }

        if ($this->posicion) {
            $mail->line("Vas **#{$this->posicion}** en la clasificación{$this->textoCambio()}.");
        }

        return $mail
            ->action('Ver clasificación', url('/clasificacion'))
            ->line('Sigue así para la próxima jornada — cada pronóstico cuenta.')
            ->salutation('¡Nos vemos en la próxima jornada! ⚽');
    }

    private function desglose(array $r): string
    {
        $lineas = [];

        $detalle = '';
        if ($r['partidos'] > 0) {
            $detalle = " _({$r['aciertos']} de {$r['partidos']} acertados";
            if ($r['exactos'] > 0) {
                $detalle .= ', '.$r['exactos'].' '.($r['exactos'] === 1 ? 'exacto' : 'exactos');
            }
            $detalle .= ')_';
        }
        $lineas[] = "- ⚽ Pronósticos: **{$r['pronosticos']}**{$detalle}";

        if ($r['bonus_pleno'] > 0) {
            $lineas[] = "- 🏆 Bonus de pleno: **+{$r['bonus_pleno']}**";
        }

        if ($r['goleadores_elegidos'] > 0) {
            $quien = $r['goleadores']
                ? implode(', ', array_map(fn ($g) => $g['nombre'].' '.$g['goles'].' '.($g['goles'] === 1 ? 'gol' : 'goles'), $r['goleadores']))
                : 'ninguno de tus goleadores marcó';
            $lineas[] = "- 🎯 Goleadores: **{$r['goleadores_puntos']}** _({$quien})_";
        }

        if ($r['mostrar_cartas']) {
            $lineas[] = '- 🃏 Cartas: **'.$this->conSigno($r['cartas']).'**';
        }

        return implode("\n", $lineas);
    }

    /** Texto de la flecha, siempre con palabras (nunca solo el símbolo). */
    private function textoCambio(): string
    {
        $cambio = $this->resumen['cambio_posicion'] ?? null;

        if ($cambio === null) {
            return '';
        }
        if ($cambio > 0) {
            return " (▲ subes {$cambio} ".($cambio === 1 ? 'puesto' : 'puestos').')';
        }
        if ($cambio < 0) {
            $bajada = abs($cambio);

            return " (▼ bajas {$bajada} ".($bajada === 1 ? 'puesto' : 'puestos').')';
        }

        return ' (= mantienes el puesto)';
    }

    private function flechaCorta(): string
    {
        $cambio = $this->resumen['cambio_posicion'] ?? null;

        if ($cambio === null || $cambio === 0) {
            return '';
        }

        return $cambio > 0 ? " ▲{$cambio}" : ' ▼'.abs($cambio);
    }

    private function conSigno(int $n): string
    {
        return $n > 0 ? "+{$n}" : (string) $n;
    }
}
