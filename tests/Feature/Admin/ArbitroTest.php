<?php

namespace Tests\Feature\Admin;

use App\Models\Arbitro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArbitroTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_arbitro(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/arbitros', [
            'nombre' => 'Mateo',
            'apellidos' => 'Busquets Ferrer',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('arbitros', ['nombre' => 'Mateo', 'apellidos' => 'Busquets Ferrer']);
    }

    public function test_un_admin_puede_editar_todos_los_campos_de_un_arbitro(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $arbitro = Arbitro::factory()->create();

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/arbitros/{$arbitro->id}", [
            'nombre' => $arbitro->nombre,
            'imagen' => 'https://ejemplo.com/foto.jpg',
            'promedio_tarjetas_amarillas' => 4.8,
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('arbitros', [
            'id' => $arbitro->id,
            'imagen' => 'https://ejemplo.com/foto.jpg',
        ]);
    }

    public function test_un_admin_puede_eliminar_un_arbitro(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $arbitro = Arbitro::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/arbitros/{$arbitro->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('arbitros', ['id' => $arbitro->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_arbitros(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/arbitros');

        $respuesta->assertStatus(403);
    }
}