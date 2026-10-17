<?php

namespace App\Listeners;

use App\Models\PushSuscripcion;
use App\Models\User;
use App\Services\AvisosPush;
use App\Services\PushService;
use Illuminate\Notifications\Events\NotificationSent;

use function Illuminate\Support\defer;

/**
 * Cada notificación que llega a la campana se manda también al móvil, si el
 * usuario tiene algún dispositivo activado y quiere ese tipo de aviso. Así no
 * hay que tocar las notificaciones una a una: mismos textos que la campana.
 *
 * El envío se hace justo después de responder (defer): quien juega una Falta
 * o cierra la jornada no espera a que Google o Apple contesten.
 */
class EnviarAvisoAlMovil
{
    public function __construct(private AvisosPush $avisos) {}

    public function handle(NotificationSent $evento): void
    {
        if ($evento->channel !== 'database' || ! $evento->notifiable instanceof User) {
            return;
        }

        $usuario = $evento->notifiable;
        $datos = (array) ($evento->response->data ?? []);
        $tipo = $datos['tipo'] ?? null;
        $categoria = $this->avisos->categoriaDe($tipo, $usuario);

        if (! $categoria || ! ($this->avisos->preferencias($usuario)[$categoria] ?? false)) {
            return;
        }
        if (! PushSuscripcion::where('id_usuario', $usuario->id)->exists()) {
            return;
        }

        $titulo = (string) ($datos['titulo'] ?? 'PronostiCup');
        $cuerpo = (string) ($datos['mensaje'] ?? '');
        $url = $this->avisos->urlDe($tipo, $datos);
        $etiqueta = $this->avisos->etiquetaDe($tipo, $datos);

        defer(function () use ($usuario, $titulo, $cuerpo, $url, $etiqueta) {
            app(PushService::class)->enviarA($usuario, $titulo, $cuerpo, $url, $etiqueta, $usuario->unreadNotifications()->count());
        });
    }
}
