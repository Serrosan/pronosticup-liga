<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\PushSuscripcion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Admin → Enviar aviso: llega a la campana de los destinatarios y a su móvil. */
class AvisosAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Liga $liga;
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['es_superadmin' => true])->save();
        $this->liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'Normal']);

        $this->mock(PushService::class, function ($mock) {
            $mock->shouldReceive('configurado')->andReturn(true);
            $mock->shouldReceive('enviarA')->andReturnUsing(function ($usuario, $titulo, $cuerpo, $url) {
                $this->enviados[] = ['id_usuario' => $usuario->id, 'url' => $url];

                return ['enviados' => 1, 'caducadas' => 0, 'fallidos' => 0];
            });
        });
    }

    private function miembro(bool $conMovil = false): User
    {
        $usuario = User::factory()->create();
        $this->liga->usuarios()->attach($usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
        if ($conMovil) {
            PushSuscripcion::create([
                'id_usuario' => $usuario->id, 'endpoint' => "https://fcm.googleapis.com/{$usuario->id}",
                'endpoint_hash' => PushSuscripcion::huella("https://fcm.googleapis.com/{$usuario->id}"), 'p256dh' => 'a', 'auth' => 'b',
            ]);
        }

        return $usuario;
    }

    public function test_el_aviso_llega_a_la_campana_de_la_liga_y_al_movil_de_quien_lo_tiene(): void
    {
        $conMovil = $this->miembro(conMovil: true);
        $sinMovil = $this->miembro();
        $deFuera = User::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/avisos', [
            'para' => (string) $this->liga->id, 'titulo' => '📅 Aplazado el miércoles',
            'mensaje' => 'A las 21:00.', 'url' => '/jornadas',
        ])->assertOk()->assertJsonFragment(['message' => 'Enviado a 2 persona(s). 1 lo recibirán también en el móvil (si tienen activados los avisos del admin).']);

        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame('aviso_admin', $sinMovil->notifications()->first()->data['tipo']);
        $this->assertSame(1, $conMovil->notifications()->count());
        $this->assertSame(0, $deFuera->notifications()->count());
        $this->assertSame([['id_usuario' => $conMovil->id, 'url' => '/jornadas']], $this->enviados);
    }

    public function test_solo_el_superadmin_y_solo_destinos_conocidos(): void
    {
        Sanctum::actingAs($this->miembro());
        $this->getJson('/api/v1/admin/avisos')->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/avisos')->assertOk()->assertJsonPath('data.ligas.0.personas', 1);
        $this->postJson('/api/v1/admin/avisos', ['para' => 'todos', 'titulo' => 'x', 'mensaje' => 'y', 'url' => 'https://otra-web.com'])
            ->assertStatus(422);
    }
}
