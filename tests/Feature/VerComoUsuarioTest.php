<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Ver como un usuario": solo el superadmin, solo para mirar.
 */
class VerComoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $juan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'admin@ejemplo.test']);
        $this->admin->forceFill(['es_superadmin' => true])->save();
        $this->juan = User::factory()->create(['email' => 'juan@ejemplo.test']);
    }

    public function test_el_superadmin_ve_la_app_como_otro_usuario(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/me', ['X-Ver-Como' => $this->juan->id])
            ->assertOk()
            ->assertSee('juan@ejemplo.test')
            ->assertDontSee('admin@ejemplo.test');
    }

    public function test_sin_la_cabecera_todo_sigue_igual(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/me')->assertOk()->assertSee('admin@ejemplo.test');
    }

    public function test_un_usuario_normal_no_puede_hacerse_pasar_por_otro(): void
    {
        $pedro = User::factory()->create(['email' => 'pedro@ejemplo.test']);
        Sanctum::actingAs($pedro);

        $this->getJson('/api/v1/me', ['X-Ver-Como' => $this->juan->id])
            ->assertOk()
            ->assertSee('pedro@ejemplo.test')
            ->assertDontSee('juan@ejemplo.test');
    }

    public function test_viendo_como_otro_no_se_puede_guardar_nada(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/pronosticos', ['id_partido' => 1], ['X-Ver-Como' => $this->juan->id])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Estás viendo la app como otro usuario: en este modo no se puede cambiar nada.');
    }

    public function test_un_usuario_que_no_existe_da_404(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/me', ['X-Ver-Como' => 999999])->assertNotFound();
    }

    public function test_el_admin_tiene_la_lista_de_usuarios_para_elegir(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/ver-como/usuarios')
            ->assertOk()
            ->assertJsonFragment(['email' => 'juan@ejemplo.test']);
    }
}
