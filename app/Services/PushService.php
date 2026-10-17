<?php

namespace App\Services;

use App\Models\PushSuscripcion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envía avisos al móvil (Web Push) a los dispositivos de un usuario.
 *
 * Necesita las claves VAPID en el .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
 * se generan una vez con `php artisan push:claves`. Sin ellas no envía nada y
 * la sección del perfil no aparece.
 *
 * Los dispositivos que el servicio del móvil da por caducados (app
 * desinstalada, permiso retirado...) se borran solos al intentar enviarles.
 */
class PushService
{
    public function configurado(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function clavePublica(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * @param  string|null  $etiqueta  Avisos con la misma etiqueta se sustituyen en vez de apilarse.
     * @param  int|null  $pendientes  Número para el puntito del icono de la app (sin leer).
     * @return array{enviados:int, caducadas:int, fallidos:int}
     */
    public function enviarA(User $usuario, string $titulo, string $cuerpo, string $url = '/dashboard', ?string $etiqueta = null, ?int $pendientes = null): array
    {
        return $this->enviar(
            PushSuscripcion::where('id_usuario', $usuario->id)->get(),
            array_filter(['titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $url, 'tag' => $etiqueta, 'pendientes' => $pendientes], fn ($v) => $v !== null)
        );
    }

    /** @return array{enviados:int, caducadas:int, fallidos:int} */
    protected function enviar(Collection $suscripciones, array $contenido): array
    {
        $resultado = ['enviados' => 0, 'caducadas' => 0, 'fallidos' => 0];

        if (! $this->configurado() || $suscripciones->isEmpty()) {
            return $resultado;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ], ['TTL' => 86400, 'urgency' => 'high']);

        $porEndpoint = $suscripciones->keyBy('endpoint');
        $carga = json_encode($contenido, JSON_UNESCAPED_UNICODE);

        foreach ($suscripciones as $suscripcion) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $suscripcion->endpoint,
                'publicKey' => $suscripcion->p256dh,
                'authToken' => $suscripcion->auth,
                'contentEncoding' => 'aes128gcm',
            ]), $carga);
        }

        foreach ($webPush->flush() as $informe) {
            $suscripcion = $porEndpoint->get($informe->getEndpoint());

            if ($informe->isSuccess()) {
                $resultado['enviados']++;
                $suscripcion?->update(['ultimo_envio_en' => now()]);
            } elseif ($informe->isSubscriptionExpired()) {
                $resultado['caducadas']++;
                $suscripcion?->delete();
            } else {
                $resultado['fallidos']++;
                Log::warning('Aviso al móvil no entregado: '.$informe->getReason());
            }
        }

        return $resultado;
    }
}
