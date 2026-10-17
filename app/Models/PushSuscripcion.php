<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un dispositivo (navegador o app instalada) con los avisos al móvil activados. */
class PushSuscripcion extends Model
{
    protected $table = 'push_suscripciones';

    protected $fillable = ['id_usuario', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'user_agent', 'ultimo_envio_en'];

    protected function casts(): array
    {
        return ['ultimo_envio_en' => 'datetime'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public static function huella(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
