<?php

namespace Tests\Feature\Admin;

use App\Models\Trofeo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrofeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_trofeo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/trofeos', [
            'nombre' => 'LaLiga',
            'tipo' => 'Colectivo',
            'ambito' => 'Nacional',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('trofeos', ['nombre' => 'LaLiga']);
    }

    public function test_un_admin_puede_editar_un_trofeo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $trofeo = Trofeo::factory()->create();

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/trofeos/{$trofeo->id}", [
            'nombre' => $trofeo->nombre,
            'imagen' => 'https://ejemplo.com/trofeo.jpg',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('trofeos', ['id' => $trofeo->id, 'imagen' => 'https://ejemplo.com/trofeo.jpg']);
    }

    public function test_un_admin_puede_eliminar_un_trofeo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $trofeo = Trofeo::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/trofeos/{$trofeo->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('trofeos', ['id' => $trofeo->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_trofeos(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/trofeos');

        $respuesta->assertStatus(403);
    }
}