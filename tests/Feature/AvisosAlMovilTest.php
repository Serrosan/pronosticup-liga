<?php

namespace Tests\Feature;

use App\Models\PushSuscripcion;
use App\Models\User;
use App\Notifications\FaltaJugadaContraTi;
use App\Notifications\JornadaCerradaConPuntos;
use App\Notifications\MensajeReaccionado;
use App\Notifications\SincronizacionFallida;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase 2: lo que llega a la campana se manda también al móvil, según lo que
 * cada uno haya elegido. El envío real se simula.
 */
class AvisosAlMovilTest extends TestCase
{
    use RefreshDatabase;

    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PushService::class, function ($mock) {
            $mock->shouldReceive('configurado')->andReturn(true);
            $mock->shouldReceive('clavePublica')->andReturn('clave');
            $mock->shouldReceive('enviarA')->andReturnUsing(function ($usuario, $titulo, $cuerpo, $url, $etiqueta = null, $pendientes = null) {
                $this->enviados[] = compact('titulo', 'cuerpo', 'url', 'etiqueta', 'pendientes') + ['id_usuario' => $usuario->id];

                return ['enviados' => 1, 'caducadas' => 0, 'fallidos' => 0];
            });
        });
    }

    private function usuarioConMovil(bool $admin = false): User
    {
        $usuario = User::factory()->create(['recibir_email_puntos' => false]);
        if ($admin) {
            $usuario->forceFill(['es_superadmin' => true])->save();
        }
        PushSuscripcion::create([
            'id_usuario' => $usuario->id, 'endpoint' => "https://fcm.googleapis.com/x/{$usuario->id}",
            'endpoint_hash' => PushSuscripcion::huella("https://fcm.googleapis.com/x/{$usuario->id}"),
            'p256dh' => 'a', 'auth' => 'b',
        ]);

        return $usuario;
    }

    /** Lo que se manda "después de responder" se lanza aquí a mano. */
    private function enviarPendientes(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }

    public function test_una_falta_llega_al_movil_con_el_texto_de_la_campana_y_lleva_a_mis_cartas(): void
    {
        $jorge = $this->usuarioConMovil();

        $jorge->notify(new FaltaJugadaContraTi('Roberto', 'Resultado Gafas', 10));
        $this->enviarPendientes();

        $this->assertCount(1, $this->enviados);
        $this->assertSame('⚠️ Te han jugado una Falta', $this->enviados[0]['titulo']);
        $this->assertStringContainsString('Roberto', $this->enviados[0]['cuerpo']);
        $this->assertSame('/mis-cartas', $this->enviados[0]['url']);
        $this->assertSame(1, $this->enviados[0]['pendientes'], 'El numerito del icono: 1 sin leer');
    }

    public function test_la_jornada_cerrada_lleva_a_la_pestana_de_la_liga_de_esa_jornada(): void
    {
        $this->usuarioConMovil()->notify(new JornadaCerradaConPuntos(9, 18, 3, 'Basiliga'));
        $this->enviarPendientes();

        $this->assertSame('/jornadas/9/liga', $this->enviados[0]['url']);
        $this->assertSame('jornada-9', $this->enviados[0]['etiqueta']);
    }

    public function test_si_desactivas_un_tipo_no_te_llega_y_las_reacciones_vienen_apagadas(): void
    {
        $ana = $this->usuarioConMovil();
        Sanctum::actingAs($ana);
        $this->patchJson('/api/v1/push/preferencias', ['clave' => 'faltas', 'activa' => false])->assertOk();

        $ana->fresh()->notify(new FaltaJugadaContraTi('Roberto', 'Resultado Gafas', 10));
        $ana->fresh()->notify(new MensajeReaccionado('Bea', '😂'));
        $this->enviarPendientes();

        $this->assertSame([], $this->enviados);
    }

    public function test_sin_dispositivos_no_se_intenta_enviar(): void
    {
        User::factory()->create()->notify(new FaltaJugadaContraTi('Roberto', 'Resultado Gafas', 10));
        $this->enviarPendientes();

        $this->assertSame([], $this->enviados);
    }

    public function test_los_avisos_de_admin_solo_los_ve_el_superadmin(): void
    {
        $admin = $this->usuarioConMovil(admin: true);
        $admin->notify(new SincronizacionFallida('timeout'));
        $this->enviarPendientes();
        $this->assertSame('/admin/tareas', $this->enviados[0]['url']);

        Sanctum::actingAs($this->usuarioConMovil());
        $claves = collect($this->getJson('/api/v1/push/config')->assertOk()->json('data.preferencias'))->pluck('clave');
        $this->assertNotContains('admin', $claves);
        $this->assertContains('faltas', $claves);
        $this->patchJson('/api/v1/push/preferencias', ['clave' => 'admin', 'activa' => true])->assertStatus(422);
    }
}
