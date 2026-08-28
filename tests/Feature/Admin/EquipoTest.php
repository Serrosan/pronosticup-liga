<?php

namespace Tests\Feature\Admin;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_equipo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/equipos', [
            'nombre' => 'Real Madrid Club de Fútbol',
            'nombre_corto' => 'Real Madrid',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('equipos', ['nombre' => 'Real Madrid Club de Fútbol']);
    }

    public function test_un_admin_puede_editar_un_equipo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $equipo = Equipo::factory()->create(['nombre' => 'Nombre viejo']);

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/equipos/{$equipo->id}", [
            'nombre' => 'Nombre nuevo',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id, 'nombre' => 'Nombre nuevo']);
    }

    public function test_un_admin_puede_eliminar_un_equipo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $equipo = Equipo::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/equipos/{$equipo->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('equipos', ['id' => $equipo->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_equipos(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/equipos');

        $respuesta->assertStatus(403);
    }
}