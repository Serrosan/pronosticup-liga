<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\PrediccionQuiniela;
use App\Models\QuinielaPosiciones;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuinielaTest extends TestCase
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

    private function superadmin(int $idLiga): User
    {
        return User::factory()->create(['liga_activa_id' => $idLiga, 'es_superadmin' => true]);
    }

    private function crear4Equipos(): array
    {
        return [
            Equipo::factory()->create(['nombre' => 'Equipo A']),
            Equipo::factory()->create(['nombre' => 'Equipo B']),
            Equipo::factory()->create(['nombre' => 'Equipo C']),
            Equipo::factory()->create(['nombre' => 'Equipo D']),
        ];
    }

    public function test_se_puede_guardar_una_prediccion_completa_en_quiniela_abierta(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        [$a, $b, $c, $d] = $this->crear4Equipos();
        QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => true]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 2],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
                ['id_equipo' => $d->id, 'posicion_predicha' => 4],
            ],
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('predicciones_quiniela', 4);
    }

    public function test_no_se_puede_guardar_si_falta_algun_equipo(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        [$a, $b, $c] = $this->crear4Equipos();
        QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => true]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 2],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
            ],
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_no_se_puede_guardar_con_posiciones_repetidas(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        [$a, $b, $c, $d] = $this->crear4Equipos();
        QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => true]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 1],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
                ['id_equipo' => $d->id, 'posicion_predicha' => 4],
            ],
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_no_se_puede_guardar_si_la_quiniela_no_esta_abierta(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        [$a, $b, $c, $d] = $this->crear4Equipos();
        QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => false]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 2],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
                ['id_equipo' => $d->id, 'posicion_predicha' => 4],
            ],
        ]);

        $respuesta->assertStatus(422);
    }

    public function test_guardar_de_nuevo_sustituye_la_prediccion_anterior(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        [$a, $b, $c, $d] = $this->crear4Equipos();
        QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => true]);

        $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 2],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
                ['id_equipo' => $d->id, 'posicion_predicha' => 4],
            ],
        ]);

        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 4],
                ['id_equipo' => $b->id, 'posicion_predicha' => 3],
                ['id_equipo' => $c->id, 'posicion_predicha' => 2],
                ['id_equipo' => $d->id, 'posicion_predicha' => 1],
            ],
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseCount('predicciones_quiniela', 4);
        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $a->id, 'posicion_predicha' => 4]);
    }

    public function test_resolver_calcula_los_puntos_segun_la_clasificacion_real(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $admin = $this->superadmin($liga->id);
        [$a, $b, $c, $d] = $this->crear4Equipos();

        // Todos contra todos, sin empates de puntos: A gana todo (9pts, 1º), B gana a C y D (6pts, 2º), C gana a D (3pts, 3º), D pierde todo (0pts, 4º)
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Jugado', 'id_equipo_local' => $a->id, 'id_equipo_visitante' => $b->id, 'goles_casa' => 2, 'goles_fuera' => 0]);
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 2, 'estado' => 'Jugado', 'id_equipo_local' => $a->id, 'id_equipo_visitante' => $c->id, 'goles_casa' => 2, 'goles_fuera' => 0]);
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 3, 'estado' => 'Jugado', 'id_equipo_local' => $a->id, 'id_equipo_visitante' => $d->id, 'goles_casa' => 2, 'goles_fuera' => 0]);
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 4, 'estado' => 'Jugado', 'id_equipo_local' => $b->id, 'id_equipo_visitante' => $c->id, 'goles_casa' => 2, 'goles_fuera' => 0]);
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 5, 'estado' => 'Jugado', 'id_equipo_local' => $b->id, 'id_equipo_visitante' => $d->id, 'goles_casa' => 2, 'goles_fuera' => 0]);
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 6, 'estado' => 'Jugado', 'id_equipo_local' => $c->id, 'id_equipo_visitante' => $d->id, 'goles_casa' => 2, 'goles_fuera' => 0]);

        // Puntos reales: A=9(1º) B=6(2º) C=3(3º) D=0(4º), sin necesidad de desempate por diferencia de goles

        $quiniela = QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'abierta' => true]);

        // Predicción del usuario: A=1(real1,dif0->5pts), B=4(real2,dif2->3pts), C=2(real3,dif1->4pts), D=3(real4,dif1->4pts)
        $this->actingAs($usuario)->postJson('/api/v1/quinielas/completa', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 4],
                ['id_equipo' => $c->id, 'posicion_predicha' => 2],
                ['id_equipo' => $d->id, 'posicion_predicha' => 3],
            ],
        ]);

        $respuesta = $this->actingAs($admin)->postJson("/api/v1/admin/quinielas/{$quiniela->id}/resolver");
        $respuesta->assertStatus(200);

        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $a->id, 'puntos_obtenidos' => 5]);
        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $b->id, 'puntos_obtenidos' => 3]);
        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $c->id, 'puntos_obtenidos' => 4]);
        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $d->id, 'puntos_obtenidos' => 4]);

        $consultaResuelta = $this->actingAs($usuario)->getJson('/api/v1/quinielas/completa');
        $consultaResuelta->assertJsonPath('data.resuelta', true);
        $consultaResuelta->assertJsonPath('data.puntos_totales', 16);
    }

    public function test_primera_mitad_solo_cuenta_partidos_hasta_la_jornada_18(): void
    {
        [$usuario, $liga, $temporada] = $this->usuarioConLiga();
        $admin = $this->superadmin($liga->id);
        [$a, $b, $c, $d] = $this->crear4Equipos();

        // Hasta J18: A gana a B (A queda 1º, B 2º, C y D empatados a 0)
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 5, 'estado' => 'Jugado', 'id_equipo_local' => $a->id, 'id_equipo_visitante' => $b->id, 'goles_casa' => 1, 'goles_fuera' => 0]);

        // En J19 (fuera del rango de la primera mitad) D golea a A - si esto se colara, cambiaría el 1er puesto
        CalendarioPartido::factory()->create(['id_temporada' => $temporada->id, 'jornada' => 19, 'estado' => 'Jugado', 'id_equipo_local' => $d->id, 'id_equipo_visitante' => $a->id, 'goles_casa' => 5, 'goles_fuera' => 0]);

        $quiniela = QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'primera_mitad', 'abierta' => true]);

        $this->actingAs($usuario)->postJson('/api/v1/quinielas/primera_mitad', [
            'predicciones' => [
                ['id_equipo' => $a->id, 'posicion_predicha' => 1],
                ['id_equipo' => $b->id, 'posicion_predicha' => 2],
                ['id_equipo' => $c->id, 'posicion_predicha' => 3],
                ['id_equipo' => $d->id, 'posicion_predicha' => 4],
            ],
        ]);

        $this->actingAs($admin)->postJson("/api/v1/admin/quinielas/{$quiniela->id}/resolver");

        // Si el sistema respetó el corte en J18, A sigue siendo 1º pese al golazo de D en J19 -> acierto exacto (3pts, exacta=3 en primera_mitad)
        $this->assertDatabaseHas('predicciones_quiniela', ['id_equipo' => $a->id, 'puntos_obtenidos' => 3]);
    }

    public function test_no_se_puede_resolver_dos_veces_la_misma_quiniela(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        $admin = $this->superadmin($liga->id);
        $quiniela = QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'resuelta' => true, 'resuelta_en' => now()]);

        $respuesta = $this->actingAs($admin)->postJson("/api/v1/admin/quinielas/{$quiniela->id}/resolver");

        $respuesta->assertStatus(409);
    }

    public function test_no_se_puede_reabrir_una_quiniela_ya_resuelta(): void
    {
        [$usuario, $liga] = $this->usuarioConLiga();
        $admin = $this->superadmin($liga->id);
        $quiniela = QuinielaPosiciones::create(['id_liga' => $liga->id, 'tipo' => 'completa', 'resuelta' => true, 'resuelta_en' => now()]);

        $respuesta = $this->actingAs($admin)->postJson("/api/v1/admin/quinielas/{$quiniela->id}/abrir");

        $respuesta->assertStatus(422);
    }
}