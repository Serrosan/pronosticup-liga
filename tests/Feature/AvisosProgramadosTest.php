<?php

namespace Tests\Feature;

use App\Models\AvisoProgramado;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Avisos del admin programados: se guardan, salen a su hora una sola vez y se pueden cancelar. */
class AvisosProgramadosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $jugador;
    private Liga $liga;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-19 12:00:00');
        $this->mock(PushService::class, fn ($m) => $m->shouldReceive('configurado')->andReturn(true)->getMock()->shouldReceive('enviarA')->andReturn(['enviados' => 0, 'caducadas' => 0, 'fallidos' => 0]));

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['es_superadmin' => true])->save();
        $this->jugador = User::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'Normal']);
        $this->liga->usuarios()->attach($this->jugador->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
        Sanctum::actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function programar(string $cuando)
    {
        return $this->postJson('/api/v1/admin/avisos', [
            'para' => (string) $this->liga->id, 'titulo' => '📅 Aplazado', 'mensaje' => 'Mañana a las 20:00.', 'url' => '/jornadas',
            'enviar_en' => $cuando,
        ]);
    }

    public function test_se_programa_y_sale_a_su_hora_una_sola_vez(): void
    {
        $this->programar(Carbon::parse('2026-10-21 17:00:00')->toIso8601String())->assertOk();
        $this->assertSame(0, $this->jugador->notifications()->count(), 'Aún no se ha enviado');
        $this->getJson('/api/v1/admin/avisos')->assertJsonPath('data.programados.0.titulo', '📅 Aplazado');

        Carbon::setTestNow('2026-10-21 16:59:00');
        $this->artisan('avisos:enviar-programados')->assertSuccessful();
        $this->assertSame(0, $this->jugador->notifications()->count(), 'Un minuto antes no sale');

        Carbon::setTestNow('2026-10-21 17:00:30');
        $this->artisan('avisos:enviar-programados')->assertSuccessful();
        $this->artisan('avisos:enviar-programados')->assertSuccessful();

        $this->assertSame(1, $this->jugador->notifications()->count(), 'Sale una vez');
        $this->assertSame('aviso_admin', $this->jugador->notifications()->first()->data['tipo']);
        $this->assertSame(1, (int) AvisoProgramado::first()->personas);
        $this->getJson('/api/v1/admin/avisos')->assertJsonCount(0, 'data.programados');
    }

    public function test_se_puede_cancelar_y_no_sale(): void
    {
        $id = $this->programar(Carbon::parse('2026-10-21 17:00:00')->toIso8601String())->json('data.id');

        $this->deleteJson("/api/v1/admin/avisos/{$id}")->assertOk();

        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->artisan('avisos:enviar-programados')->assertSuccessful();
        $this->assertSame(0, $this->jugador->notifications()->count());
    }

    public function test_no_se_puede_programar_en_el_pasado(): void
    {
        $this->programar(Carbon::parse('2026-10-19 09:00:00')->toIso8601String())->assertStatus(422);
        $this->assertSame(0, AvisoProgramado::count());
    }
}
