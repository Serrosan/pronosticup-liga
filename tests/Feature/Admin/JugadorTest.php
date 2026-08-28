<?php

namespace Tests\Feature\Admin;

use App\Models\Jugador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JugadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_puede_crear_un_jugador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/jugadores', [
            'nombre' => 'Kylian',
            'apellidos' => 'Mbappé',
            'posicion' => 'Delantero',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('jugadores', ['nombre' => 'Kylian', 'apellidos' => 'Mbappé']);
    }

    public function test_un_admin_puede_editar_todos_los_campos_de_un_jugador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $jugador = Jugador::factory()->create();

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/jugadores/{$jugador->id}", [
            'nombre' => $jugador->nombre,
            'posicion_detallada' => 'Lateral derecho',
            'pie' => 'Derecho',
            'altura' => 178,
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('jugadores', [
            'id' => $jugador->id,
            'posicion_detallada' => 'Lateral derecho',
            'pie' => 'Derecho',
            'altura' => 178,
        ]);
    }

    public function test_un_admin_puede_eliminar_un_jugador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        $jugador = Jugador::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/jugadores/{$jugador->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('jugadores', ['id' => $jugador->id]);
    }

    public function test_un_usuario_normal_no_puede_gestionar_jugadores(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/jugadores');

        $respuesta->assertStatus(403);
    }
}