<?php

namespace Tests\Feature\Admin;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function comoAdmin(): User
    {
        return User::factory()->create(['es_superadmin' => true]);
    }

    public function test_un_admin_puede_crear_una_nota_de_calendario(): void
    {
        $admin = $this->comoAdmin();

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/eventos-calendario', [
            'titulo' => 'Nations League',
            'fecha_inicio' => '2026-09-20',
            'fecha_fin' => '2026-10-09',
            'color' => '#FF0000',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('eventos_calendario', [
            'titulo' => 'Nations League',
            'fecha_inicio' => '2026-09-20',
            'fecha_fin' => '2026-10-09',
        ]);
    }

    public function test_las_fechas_no_se_desplazan_un_dia_al_guardar(): void
    {
        $admin = $this->comoAdmin();
        $evento = EventoCalendario::factory()->create([
            'fecha_inicio' => '2026-09-20',
            'fecha_fin' => '2026-10-09',
        ]);

        $respuesta = $this->actingAs($admin)->getJson("/api/v1/admin/eventos-calendario/{$evento->id}");

        $respuesta->assertJsonPath('data.fecha_inicio', '2026-09-20');
        $respuesta->assertJsonPath('data.fecha_fin', '2026-10-09');
    }

    public function test_un_admin_puede_editar_una_nota_existente_y_se_refleja_de_verdad(): void
    {
        $admin = $this->comoAdmin();
        $evento = EventoCalendario::factory()->create(['titulo' => 'Título original']);

        $respuesta = $this->actingAs($admin)->putJson("/api/v1/admin/eventos-calendario/{$evento->id}", [
            'titulo' => 'Título editado',
            'fecha_inicio' => '2026-09-20',
            'fecha_fin' => '2026-10-09',
        ]);

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('data.titulo', 'Título editado');
        $this->assertDatabaseHas('eventos_calendario', ['id' => $evento->id, 'titulo' => 'Título editado']);
    }

    public function test_un_admin_puede_eliminar_una_nota(): void
    {
        $admin = $this->comoAdmin();
        $evento = EventoCalendario::factory()->create();

        $respuesta = $this->actingAs($admin)->deleteJson("/api/v1/admin/eventos-calendario/{$evento->id}");

        $respuesta->assertStatus(200);
        $this->assertDatabaseMissing('eventos_calendario', ['id' => $evento->id]);
    }

    public function test_un_usuario_normal_no_puede_acceder_al_admin(): void
    {
        $usuarioNormal = User::factory()->create(['es_superadmin' => false]);

        $respuesta = $this->actingAs($usuarioNormal)->getJson('/api/v1/admin/eventos-calendario');

        $respuesta->assertStatus(403);
    }

    public function test_fechas_invalidas_dan_error_de_validacion_no_un_500(): void
    {
        $admin = $this->comoAdmin();

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/admin/eventos-calendario', [
            'titulo' => 'Evento con fechas al revés',
            'fecha_inicio' => '2026-10-09',
            'fecha_fin' => '2026-09-20',
        ]);

        $respuesta->assertStatus(422);
    }
}