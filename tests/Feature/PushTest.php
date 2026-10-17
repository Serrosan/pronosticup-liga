<?php

namespace Tests\Feature;

use App\Models\PushSuscripcion;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Avisos al móvil, fase 1: activar/desactivar por dispositivo y la prueba.
 * Nunca se sale a internet: el envío real se simula.
 */
class PushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    private function suscripcion(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth']];
    }

    public function test_sin_claves_el_servidor_dice_que_no_esta_configurado(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/push/config')->assertOk()->assertJsonPath('data.configurado', false);
        $this->postJson('/api/v1/push/prueba')->assertStatus(409);
    }

    public function test_activar_dos_veces_el_mismo_dispositivo_no_lo_duplica_y_desactivar_lo_quita(): void
    {
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion())->assertOk()->assertJsonPath('data.dispositivos', 1);
        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion())->assertOk()->assertJsonPath('data.dispositivos', 1);
        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion('https://web.push.apple.com/otro'))->assertJsonPath('data.dispositivos', 2);

        $this->deleteJson('/api/v1/push/suscripciones', ['endpoint' => self::ENDPOINT])->assertOk()->assertJsonPath('data.dispositivos', 1);
    }

    public function test_si_otro_usuario_entra_en_ese_navegador_el_dispositivo_pasa_a_ser_suyo(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();

        Sanctum::actingAs($ana);
        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion())->assertOk();
        Sanctum::actingAs($bea);
        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion())->assertOk();

        $this->assertSame(1, PushSuscripcion::count());
        $this->assertSame($bea->id, PushSuscripcion::first()->id_usuario);
    }

    public function test_no_acepta_direcciones_que_no_sean_https(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion('http://malo.example/x'))->assertStatus(422);
        $this->assertSame(0, PushSuscripcion::count());
    }

    public function test_la_prueba_se_envia_a_tus_dispositivos(): void
    {
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);
        $this->postJson('/api/v1/push/suscripciones', $this->suscripcion())->assertOk();

        $this->mock(PushService::class, function ($mock) use ($usuario) {
            $mock->shouldReceive('configurado')->andReturn(true);
            $mock->shouldReceive('enviarA')->once()
                ->withArgs(fn ($u) => $u->id === $usuario->id)
                ->andReturn(['enviados' => 1, 'caducadas' => 0, 'fallidos' => 0]);
        });

        $this->postJson('/api/v1/push/prueba')->assertOk()->assertJsonPath('message', 'Aviso de prueba enviado a tu dispositivo.');
    }

    public function test_sin_dispositivos_la_prueba_lo_dice(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->mock(PushService::class, fn ($mock) => $mock->shouldReceive('configurado')->andReturn(true));

        $this->postJson('/api/v1/push/prueba')->assertStatus(422);
    }
}
