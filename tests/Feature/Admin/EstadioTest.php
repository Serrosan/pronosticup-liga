<?php

namespace Tests\Feature\Admin;

use App\Models\Estadio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadioTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_estadio(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/estadios', [
            'nombre' => 'Santiago Bernabéu',
            'capacidad' => 85000,
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('estadios', ['nombre' => 'Santiago Bernabéu']);
    }

    public function test_un_admin_puede_editar_un_estadio(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $estadio = Estadio::factory()->create(['capacidad' => 40000]);

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/estadios/{$estadio->id}", [
            'nombre' => $estadio->nombre,
            'capacidad' => 45000,
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('estadios', ['id' => $estadio->id, 'capacidad' => 45000]);
    }

    public function test_un_admin_puede_eliminar_un_estadio(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $estadio = Estadio::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/estadios/{$estadio->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('estadios', ['id' => $estadio->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_estadios(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/estadios');

        $respuesta->assertStatus(403);
    }
}