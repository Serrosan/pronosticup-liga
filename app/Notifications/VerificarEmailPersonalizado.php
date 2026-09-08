<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;

class VerificarEmailPersonalizado extends VerifyEmailBase
{
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Activa tu cuenta en PronostiCup Liga ⚽')
            ->greeting('¡Bienvenido a PronostiCup Liga!')
            ->line('Ya casi estás dentro — solo falta un paso para empezar a pronosticar.')
            ->action('Activar mi cuenta', $url)
            ->line('Si no has creado esta cuenta, puedes ignorar este correo con tranquilidad.')
            ->salutation('¡Nos vemos en la próxima jornada! ⚽');
    }
}