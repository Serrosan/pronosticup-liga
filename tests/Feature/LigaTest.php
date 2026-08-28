<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LigaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_crear_una_liga_y_queda_como_admin(): void
    {
        Temporada::factory()->create();
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/ligas', [
            'nombre' => 'Liga de Prueba',
            'tipo' => 'Normal',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('liga_usuario', [
            'id_usuario' => $usuario->id,
            'rol' => 'Admin',
        ]);
        $this->assertEquals($respuesta->json('data.id'), $usuario->fresh()->liga_activa_id);
    }

    public function test_cada_liga_creada_tiene_un_codigo_de_acceso_unico(): void
    {
        Temporada::factory()->create();
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/ligas', [
            'nombre' => 'Otra Liga',
            'tipo' => 'Normal',
        ]);

        $codigo = $respuesta->json('data.codigo_acceso');
        $this->assertNotEmpty($codigo);
        $this->assertEquals(6, strlen($codigo));
    }

    public function test_un_usuario_puede_unirse_a_una_liga_con_codigo_valido(): void
    {
        Temporada::factory()->create();
        $creador = User::factory()->create();
        $liga = Liga::factory()->create(['codigo_acceso' => 'ABC123']);
        $liga->usuarios()->attach($creador->id, ['rol' => 'Admin']);

        $nuevoUsuario = User::factory()->create();

        $respuesta = $this->actingAs($nuevoUsuario)->postJson('/api/v1/ligas/unirse', [
            'codigo_acceso' => 'abc123',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('liga_usuario', [
            'id_usuario' => $nuevoUsuario->id,
            'id_liga' => $liga->id,
            'rol' => 'Miembro',
        ]);
    }

    public function test_no_se_puede_unir_dos_veces_a_la_misma_liga(): void
    {
        Temporada::factory()->create();
        $usuario = User::factory()->create();
        $liga = Liga::factory()->create(['codigo_acceso' => 'XYZ999']);
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Miembro']);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/ligas/unirse', [
            'codigo_acceso' => 'XYZ999',
        ]);

        $respuesta->assertStatus(409);
    }

    public function test_un_codigo_de_acceso_invalido_devuelve_404(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/ligas/unirse', [
            'codigo_acceso' => 'NOEXISTE',
        ]);

        $respuesta->assertStatus(404);
    }

    public function test_un_usuario_no_puede_ver_una_liga_a_la_que_no_pertenece(): void
    {
        Temporada::factory()->create();
        $liga = Liga::factory()->create();
        $usuarioAjeno = User::factory()->create();

        $respuesta = $this->actingAs($usuarioAjeno)->getJson("/api/v1/ligas/{$liga->id}");

        $respuesta->assertStatus(403);
    }
}