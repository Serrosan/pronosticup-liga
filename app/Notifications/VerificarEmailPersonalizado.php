<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;

class VerificarEmailPersonalizado extends VerifyEmailBase
{
    protected function buildMailMessage($url)
    {
        $urlFrontend = $this->convertirAUrlFrontend($url);

        return (new MailMessage)
            ->subject('⚽ Activa tu cuenta en PronostiCup Liga')
            ->greeting('¡Bienvenido a PronostiCup Liga!')
            ->line('Gracias por unirte — ya casi estás dentro.')
            ->line('Pulsa el botón de abajo para activar tu cuenta y empezar a pronosticar con tus amigos.')
            ->action('Activar mi cuenta', $urlFrontend)
            ->line('Este enlace caduca en 60 minutos por seguridad.')
            ->line('Si no has creado esta cuenta, puedes ignorar este correo con tranquilidad.')
            ->salutation('¡Nos vemos en la próxima jornada! ⚽');
    }

    private function convertirAUrlFrontend(string $urlApi): string
    {
        $partes = parse_url($urlApi);

        $rutaFrontend = str_replace('/api/v1/verify-email/', '/verificar-email/', $partes['path']);

        return $partes['scheme'].'://'.$partes['host'].$rutaFrontend.'?'.$partes['query'];
    }
}