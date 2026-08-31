<?php

namespace Tests\Feature\Admin;

use App\Models\Entrenador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntrenadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_entrenador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/entrenadores', [
            'nombre' => 'Xabi Alonso',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('entrenadores', ['nombre' => 'Xabi Alonso']);
    }

    public function test_un_admin_puede_editar_un_entrenador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $entrenador = Entrenador::factory()->create(['nombre' => 'Nombre viejo']);

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/entrenadores/{$entrenador->id}", [
            'nombre' => 'Nombre nuevo',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('entrenadores', ['id' => $entrenador->id, 'nombre' => 'Nombre nuevo']);
    }

    public function test_un_admin_puede_eliminar_un_entrenador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $entrenador = Entrenador::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/entrenadores/{$entrenador->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('entrenadores', ['id' => $entrenador->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_entrenadores(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/entrenadores');

        $respuesta->assertStatus(403);
    }
}