<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\GoleadorJornada;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoleadoresTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConLiga(): array
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);
        $usuario = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Miembro']);

        return [$usuario, $liga, $temporada];
    }

    private function partidoProgramado(int $idTemporada, int $jornada): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $idTemporada,
            'jornada' => $jornada,
            'estado' => 'Programado',
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);
    }

    public function test_se_pueden_guardar_5_goleadores_en_una_jornada_abierta(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);
        $jugadores = Jugador::factory()->count(5)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => $jugadores->pluck('id')->all(),
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('goleadores_jornada', 5);
    }

    public function test_no_se_puede_guardar_con_menos_de_5_jugadores(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);
        $jugadores = Jugador::factory()->count(3)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => $jugadores->pluck('id')->all(),
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_no_se_pueden_repetir_jugadores_en_la_misma_seleccion(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);
        $jugador = Jugador::factory()->create();
        $otros = Jugador::factory()->count(4)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => [$jugador->id, $jugador->id, ...$otros->pluck('id')->take(3)->all()],
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_si_algun_partido_de_la_jornada_ya_no_esta_programado_se_bloquea_la_seleccion(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();

        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Jugado',
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
            'goles_casa' => 1, 'goles_fuera' => 0,
        ]);

        $jugadores = Jugador::factory()->count(5)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => $jugadores->pluck('id')->all(),
        ]);

        $respuesta->assertStatus(422);
        $this->assertDatabaseCount('goleadores_jornada', 0);
    }

    public function test_no_se_puede_repetir_un_jugador_de_la_jornada_anterior(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);
        $this->partidoProgramado($temporada->id, 2);

        $jugadorRepetido = Jugador::factory()->create();
        $otrosJornada1 = Jugador::factory()->count(4)->create();

        GoleadorJornada::create([
            'id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'jornada' => 1, 'id_jugador' => $jugadorRepetido->id,
        ]);
        foreach ($otrosJornada1 as $j) {
            GoleadorJornada::create(['id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'jornada' => 1, 'id_jugador' => $j->id]);
        }

        $otrosJornada2 = Jugador::factory()->count(4)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/2/goleadores', [
            'jugadores' => [$jugadorRepetido->id, ...$otrosJornada2->pluck('id')->all()],
        ]);

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('No puedes repetir', $respuesta->json('message'));
    }

    public function test_se_puede_repetir_un_jugador_de_dos_jornadas_atras(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);
        $this->partidoProgramado($temporada->id, 2);
        $this->partidoProgramado($temporada->id, 3);

        $jugadorReutilizable = Jugador::factory()->create();

        GoleadorJornada::create([
            'id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'jornada' => 1, 'id_jugador' => $jugadorReutilizable->id,
        ]);
        foreach (Jugador::factory()->count(4)->create() as $j) {
            GoleadorJornada::create(['id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'jornada' => 1, 'id_jugador' => $j->id]);
        }
        foreach (Jugador::factory()->count(5)->create() as $j) {
            GoleadorJornada::create(['id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'jornada' => 2, 'id_jugador' => $j->id]);
        }

        $otrosJornada3 = Jugador::factory()->count(4)->create();

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/3/goleadores', [
            'jugadores' => [$jugadorReutilizable->id, ...$otrosJornada3->pluck('id')->all()],
        ]);

        $respuesta->assertStatus(200);
    }

    public function test_guardar_de_nuevo_sustituye_la_seleccion_anterior_de_esa_misma_jornada(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $this->partidoProgramado($temporada->id, 1);

        $primeraSeleccion = Jugador::factory()->count(5)->create();
        $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => $primeraSeleccion->pluck('id')->all(),
        ]);

        $segundaSeleccion = Jugador::factory()->count(5)->create();
        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/1/goleadores', [
            'jugadores' => $segundaSeleccion->pluck('id')->all(),
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('goleadores_jornada', 5);
        $this->assertDatabaseHas('goleadores_jornada', ['id_jugador' => $segundaSeleccion->first()->id]);
        $this->assertDatabaseMissing('goleadores_jornada', ['id_jugador' => $primeraSeleccion->first()->id]);
    }
}