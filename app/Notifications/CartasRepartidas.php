<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CartasRepartidas extends Notification
{
    use Queueable;

    public function __construct(
        public int $cantidad,
        public ?int $jornada = null,
        public ?int $posicionTop3 = null,
        public bool $sobreElTope = false,
        public bool $inicial = false,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $nuevas = $this->cantidad === 1 ? 'una carta nueva' : "{$this->cantidad} cartas nuevas";
        $abrir = $this->cantidad === 1 ? 'Ábrela' : 'Ábrelas';

        if ($this->inicial) {
            $mensaje = "Arranca el modo Cartas: el admin te ha repartido {$nuevas}. {$abrir} desde Mis Cartas.";
        } else {
            $mensaje = "Reparto de la jornada {$this->jornada}: has recibido {$nuevas}.";

            if ($this->posicionTop3) {
                $mensaje .= " Una de ellas es tu bonus por quedar {$this->posicionTop3}º en la jornada.";
            }

            $mensaje .= " {$abrir} desde Mis Cartas.";
        }

        if ($this->sobreElTope) {
            $mensaje .= ' Ojo: superas el tope de tu mano, tendrás que descartar alguna para poder jugar.';
        }

        return [
            'tipo' => 'cartas_repartidas',
            'titulo' => '🃏 Nuevas cartas',
            'mensaje' => $mensaje,
            'importante' => true,
        ];
    }
}