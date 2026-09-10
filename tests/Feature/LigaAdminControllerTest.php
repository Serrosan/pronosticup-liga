<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LigaAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_una_liga_normal(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        Temporada::factory()->create();

        $respuesta = $this->postJson('/api/v1/admin/ligas', [
            'nombre' => 'Liga de prueba',
            'tipo' => 'Normal',
            'id_usuario_creador' => $admin->id,
        ]);

        $respuesta->assertCreated();
        $this->assertDatabaseHas('ligas', ['nombre' => 'Liga de prueba', 'tipo' => 'Normal']);
    }

    public function test_admin_puede_crear_una_liga_con_extras(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        Temporada::factory()->create();

        $respuesta = $this->postJson('/api/v1/admin/ligas', [
            'nombre' => 'Liga con cartas',
            'tipo' => 'ConExtras',
            'id_usuario_creador' => $admin->id,
        ]);

        $respuesta->assertCreated();
        $this->assertDatabaseHas('ligas', ['nombre' => 'Liga con cartas', 'tipo' => 'ConExtras']);
    }

    public function test_crear_liga_asigna_como_admin_al_usuario_creador(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        Temporada::factory()->create();

        $this->postJson('/api/v1/admin/ligas', [
            'nombre' => 'Liga X',
            'tipo' => 'Normal',
            'id_usuario_creador' => $admin->id,
        ]);

        $liga = Liga::where('nombre', 'Liga X')->first();

        $this->assertDatabaseHas('liga_usuario', [
            'id_liga' => $liga->id,
            'id_usuario' => $admin->id,
            'rol' => 'Admin',
        ]);
    }

    public function test_cada_liga_creada_recibe_un_codigo_de_acceso_unico(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        Temporada::factory()->create();

        $this->postJson('/api/v1/admin/ligas', ['nombre' => 'Liga A', 'tipo' => 'Normal', 'id_usuario_creador' => $admin->id]);
        $this->postJson('/api/v1/admin/ligas', ['nombre' => 'Liga B', 'tipo' => 'Normal', 'id_usuario_creador' => $admin->id]);

        $codigos = Liga::pluck('codigo_acceso');

        $this->assertCount(2, $codigos->unique());
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        Temporada::factory()->create();

        $respuesta = $this->postJson('/api/v1/admin/ligas', [
            'nombre' => 'Liga inválida',
            'tipo' => 'TipoQueNoExiste',
            'id_usuario_creador' => $admin->id,
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_usuario_no_admin_no_puede_crear_liga(): void
    {
        $usuario = User::factory()->create(['es_superadmin' => false]);
        Sanctum::actingAs($usuario);

        Temporada::factory()->create();

        $respuesta = $this->postJson('/api/v1/admin/ligas', [
            'nombre' => 'Liga no autorizada',
            'tipo' => 'Normal',
            'id_usuario_creador' => $usuario->id,
        ]);

        $respuesta->assertForbidden();
    }
}