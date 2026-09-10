<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistoricoImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearPartido(int $idTemporada, int $jornada, string $nombreLocal, string $nombreVisitante): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $idTemporada,
            'jornada' => $jornada,
            'horario_estimado' => '2026-09-20 19:00:00',
            'estado' => 'Jugado',
            'id_equipo_local' => Equipo::factory()->create(['nombre' => $nombreLocal]),
            'id_equipo_visitante' => Equipo::factory()->create(['nombre' => $nombreVisitante]),
        ]);
    }

    public function test_importa_pronosticos_emparejando_por_nombre_de_equipo(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        $usuario = User::factory()->create(['email' => 'amigo@ejemplo.com']);

        $this->crearPartido($temporada->id, 1, 'Deportivo Alavés', 'Getafe CF');

        $texto = "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tDeportivo Alavés - Getafe CF\t2\t1";

        $respuesta = $this->postJson('/api/v1/admin/importar-historico', [
            'id_liga' => $liga->id,
            'email' => 'amigo@ejemplo.com',
            'texto' => $texto,
        ]);

        $respuesta->assertOk();
        $this->assertDatabaseHas('pronosticos', [
            'id_usuario' => $usuario->id,
            'id_liga' => $liga->id,
            'goles_local_predicho' => 2,
            'goles_visitante_predicho' => 1,
            'resultado_1x2' => 'Local',
        ]);
    }

    public function test_anade_al_usuario_como_miembro_de_la_liga_si_no_lo_era(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        $usuario = User::factory()->create(['email' => 'nuevo@ejemplo.com']);

        $this->crearPartido($temporada->id, 1, 'Sevilla FC', 'Rayo Vallecano');

        $texto = "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tSevilla FC - Rayo Vallecano\t1\t0";

        $this->postJson('/api/v1/admin/importar-historico', [
            'id_liga' => $liga->id,
            'email' => 'nuevo@ejemplo.com',
            'texto' => $texto,
        ]);

        $this->assertDatabaseHas('liga_usuario', ['id_liga' => $liga->id, 'id_usuario' => $usuario->id]);
    }

    public function test_email_no_registrado_devuelve_404(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);

        $respuesta = $this->postJson('/api/v1/admin/importar-historico', [
            'id_liga' => $liga->id,
            'email' => 'no-existe@ejemplo.com',
            'texto' => "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tA - B\t1\t0",
        ]);

        $respuesta->assertStatus(404);
    }

    public function test_partido_no_encontrado_se_reporta_sin_romper_el_resto(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        User::factory()->create(['email' => 'x@ejemplo.com']);

        $this->crearPartido($temporada->id, 1, 'Real Madrid', 'Málaga CF');

        $texto = "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tEquipo Inventado - Otro Inventado\t1\t0\n1\tReal Madrid - Málaga CF\t3\t0";

        $respuesta = $this->postJson('/api/v1/admin/importar-historico', [
            'id_liga' => $liga->id,
            'email' => 'x@ejemplo.com',
            'texto' => $texto,
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('creados', 1)
            ->assertJsonPath('omitidos', 1);

        $this->assertCount(1, $respuesta->json('partidos_no_encontrados'));
    }

    public function test_es_repetible_sin_duplicar_pronosticos(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        $usuario = User::factory()->create(['email' => 'repetido@ejemplo.com']);

        $this->crearPartido($temporada->id, 1, 'Athletic Club', 'Sevilla FC');

        $texto = "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tAthletic Club - Sevilla FC\t1\t1";
        $datos = ['id_liga' => $liga->id, 'email' => 'repetido@ejemplo.com', 'texto' => $texto];

        $this->postJson('/api/v1/admin/importar-historico', $datos);
        $this->postJson('/api/v1/admin/importar-historico', $datos);

        $this->assertEquals(1, Pronostico::where('id_usuario', $usuario->id)->where('id_liga', $liga->id)->count());
    }

    public function test_marcador_no_numerico_se_omite(): void
    {
        $admin = User::factory()->create(['es_superadmin' => true]);
        Sanctum::actingAs($admin);

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        User::factory()->create(['email' => 'y@ejemplo.com']);

        $this->crearPartido($temporada->id, 1, 'Villarreal CF', 'Valencia CF');

        $texto = "Jornada\tPartido\tEquipo Local\tEquipo Visitante\n1\tVillarreal CF - Valencia CF\taplazado\t-";

        $respuesta = $this->postJson('/api/v1/admin/importar-historico', [
            'id_liga' => $liga->id,
            'email' => 'y@ejemplo.com',
            'texto' => $texto,
        ]);

        $respuesta->assertOk()->assertJsonPath('creados', 0)->assertJsonPath('omitidos', 1);
    }
}