<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PronosticoTest extends TestCase
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

    public function test_un_usuario_puede_pronosticar_un_partido_programado(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'estado' => 'Programado',
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => $partido->id,
            'resultado_1x2' => 'Local',
            'goles_local_predicho' => 2,
            'goles_visitante_predicho' => 1,
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('pronosticos', [
            'id_usuario' => $usuario->id,
            'id_partido' => $partido->id,
            'goles_local_predicho' => 2,
        ]);
    }

    public function test_no_se_puede_pronosticar_un_partido_ya_jugado(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'estado' => 'Jugado',
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => $partido->id,
            'resultado_1x2' => 'Local',
            'goles_local_predicho' => 2,
            'goles_visitante_predicho' => 1,
        ]);

        $respuesta->assertStatus(422);
        $this->assertDatabaseMissing('pronosticos', ['id_partido' => $partido->id]);
    }

    public function test_reenviar_un_pronostico_lo_actualiza_en_vez_de_duplicarlo(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'estado' => 'Programado',
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);

        $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => $partido->id, 'resultado_1x2' => 'Local',
            'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0,
        ]);

        $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => $partido->id, 'resultado_1x2' => 'Empate',
            'goles_local_predicho' => 2, 'goles_visitante_predicho' => 2,
        ]);

        $this->assertDatabaseCount('pronosticos', 1);
        $this->assertDatabaseHas('pronosticos', ['id_partido' => $partido->id, 'resultado_1x2' => 'Empate']);
    }

    public function test_un_resultado_1x2_no_valido_es_rechazado(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'estado' => 'Programado',
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => $partido->id,
            'resultado_1x2' => 'Empate a favor de nadie',
            'goles_local_predicho' => 1,
            'goles_visitante_predicho' => 1,
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_un_usuario_sin_liga_activa_no_puede_pronosticar(): void
    {
        $usuario = User::factory()->create(['liga_activa_id' => null]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/pronosticos', [
            'id_partido' => 1,
            'resultado_1x2' => 'Local',
            'goles_local_predicho' => 1,
            'goles_visitante_predicho' => 0,
        ]);

        $respuesta->assertStatus(409);
    }
}