<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorPuntosTest extends TestCase
{
    use RefreshDatabase;

    private function ligaConAdmin(): array
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        $admin = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($admin->id, ['rol' => 'Admin']);

        $this->assertDatabaseHas('liga_usuario', [
            'id_liga' => $liga->id,
            'id_usuario' => $admin->id,
            'rol' => 'Admin',
        ]);

        $this->assertEquals($liga->id, $admin->fresh()->liga_activa_id, 'liga_activa_id del usuario no coincide con la liga creada');
        $this->assertTrue(
            $liga->usuarios()->where('id_usuario', $admin->id)->wherePivot('rol', 'Admin')->exists(),
            'La consulta exists() con wherePivot no reconoce al admin como Admin'
        );

        return [$liga, $admin, $temporada];
    }

    private function partidoJugado(int $idTemporada, int $jornada, int $golesCasa, int $golesFuera): CalendarioPartido
    {
        $local = Equipo::factory()->create();
        $visitante = Equipo::factory()->create();

        return CalendarioPartido::factory()->create([
            'id_temporada' => $idTemporada,
            'jornada' => $jornada,
            'id_equipo_local' => $local->id,
            'id_equipo_visitante' => $visitante->id,
            'estado' => 'Jugado',
            'goles_casa' => $golesCasa,
            'goles_fuera' => $golesFuera,
        ]);
    }

    public function test_un_pronostico_exacto_da_3_puntos(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $partido = $this->partidoJugado($temporada->id, 1, 2, 1);

        Pronostico::create([
            'id_usuario' => $admin->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 2, 'goles_visitante_predicho' => 1,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/jornadas/1/cerrar")->assertStatus(200);

        $this->assertDatabaseHas('eventos_puntos', [
            'id_usuario' => $admin->id, 'tipo_evento' => 'AciertoExacto', 'puntos' => 3,
        ]);
    }

    public function test_acertar_solo_el_1x2_da_1_punto(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $partido = $this->partidoJugado($temporada->id, 1, 3, 1);

        Pronostico::create([
            'id_usuario' => $admin->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/jornadas/1/cerrar");

        $this->assertDatabaseHas('eventos_puntos', [
            'id_usuario' => $admin->id, 'tipo_evento' => 'Acierto1x2', 'puntos' => 1,
        ]);
    }

    public function test_fallar_del_todo_da_0_puntos(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $partido = $this->partidoJugado($temporada->id, 1, 2, 0);

        Pronostico::create([
            'id_usuario' => $admin->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Visitante', 'goles_local_predicho' => 0, 'goles_visitante_predicho' => 1,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/jornadas/1/cerrar");

        $this->assertDatabaseHas('eventos_puntos', [
            'id_usuario' => $admin->id, 'tipo_evento' => 'Fallo', 'puntos' => 0,
        ]);
    }

    public function test_no_se_puede_cerrar_una_jornada_con_partidos_pendientes(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();

        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Programado',
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/jornadas/1/cerrar');

        $respuesta->assertStatus(422);
        $this->assertDatabaseCount('eventos_puntos', 0);
    }

    public function test_no_se_puede_cerrar_dos_veces_la_misma_jornada(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $this->partidoJugado($temporada->id, 1, 1, 0);

        CierreJornada::create(['id_liga' => $liga->id, 'jornada' => 1, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $admin->id]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/jornadas/1/cerrar');

        $respuesta->assertStatus(409);
    }

    public function test_un_usuario_no_admin_de_la_liga_no_puede_cerrar_jornada(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $miembroNormal = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($miembroNormal->id, ['rol' => 'Miembro']);
        $this->partidoJugado($temporada->id, 1, 1, 0);

        $respuesta = $this->actingAs($miembroNormal)->postJson('/api/v1/jornadas/1/cerrar');

        $respuesta->assertStatus(403);
    }

    public function test_un_partido_aplazado_no_bloquea_el_cierre_del_resto_de_la_jornada(): void
    {
        [$liga, $admin, $temporada] = $this->ligaConAdmin();
        $this->partidoJugado($temporada->id, 1, 2, 0);

        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Aplazado',
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        $respuesta = $this->actingAs($admin)->postJson('/api/v1/jornadas/1/cerrar');

        $respuesta->assertStatus(200);
    }
}