<?php

namespace Tests\Feature;

use App\Models\ConfiguracionPuntos;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguracionPuntosAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'puntos_signo' => 1,
            'puntos_diferencia' => 2,
            'puntos_exacto' => 5,
            'bonus_pleno_7' => 2,
            'bonus_pleno_8' => 4,
            'bonus_pleno_9' => 8,
            'bonus_pleno_10' => 15,
            'puntos_gol_goleador' => 1,
        ], $sobrescribir);
    }

    public function test_admin_puede_ver_la_configuracion_global(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/v1/admin/configuracion-puntos/global');

        $respuesta->assertOk();
        $this->assertDatabaseHas('configuracion_puntos', ['id_liga' => null]);
    }

    public function test_admin_puede_actualizar_la_configuracion_global(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $respuesta = $this->putJson('/api/v1/admin/configuracion-puntos/global', $this->datosValidos(['puntos_exacto' => 10]));

        $respuesta->assertOk();
        $this->assertDatabaseHas('configuracion_puntos', ['id_liga' => null, 'puntos_exacto' => 10]);
    }

    public function test_usuario_no_admin_no_puede_ver_la_configuracion(): void
    {
        $usuario = User::factory()->create(['es_superadmin' => false]);
        Sanctum::actingAs($usuario);

        $respuesta = $this->getJson('/api/v1/admin/configuracion-puntos/global');

        $respuesta->assertForbidden();
    }

    public function test_admin_puede_personalizar_configuracion_de_una_liga_sin_afectar_la_global(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);

        ConfiguracionPuntos::firstOrCreate(['id_liga' => null], $this->datosValidos());

        $respuesta = $this->putJson("/api/v1/admin/configuracion-puntos/liga/{$liga->id}", $this->datosValidos(['puntos_exacto' => 20]));

        $respuesta->assertOk();
        $this->assertDatabaseHas('configuracion_puntos', ['id_liga' => $liga->id, 'puntos_exacto' => 20]);
        $this->assertDatabaseHas('configuracion_puntos', ['id_liga' => null, 'puntos_exacto' => 5]);
    }

    public function test_restaurar_global_borra_la_personalizacion_de_la_liga(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);

        ConfiguracionPuntos::create(array_merge(['id_liga' => $liga->id], $this->datosValidos(['puntos_exacto' => 20])));

        $respuesta = $this->deleteJson("/api/v1/admin/configuracion-puntos/liga/{$liga->id}");

        $respuesta->assertOk();
        $this->assertDatabaseMissing('configuracion_puntos', ['id_liga' => $liga->id]);
    }

    public function test_para_liga_devuelve_la_global_cuando_no_hay_personalizacion(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        ConfiguracionPuntos::firstOrCreate(['id_liga' => null], $this->datosValidos());

        $respuesta = $this->getJson("/api/v1/admin/configuracion-puntos/liga/{$liga->id}");

        $respuesta->assertOk()->assertJson(['personalizado' => false]);
    }
}