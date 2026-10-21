<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\ConfiguracionPuntos;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use App\Notifications\JornadaCerradaConPuntos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CorreoPuntosJornadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ConfiguracionPuntos::firstOrCreate(['id_liga' => null]);
    }

    /** Liga con un admin (superadmin, para usar la lista de cierre) y un miembro, y un partido 2-1. */
    private function escenario(): array
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id]);

        $admin = User::factory()->create(['liga_activa_id' => $liga->id]);
        $admin->forceFill(['es_superadmin' => true])->save();
        $liga->usuarios()->attach($admin->id, ['rol' => 'Admin']);

        $miembro = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($miembro->id, ['rol' => 'Miembro']);

        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Jugado',
            'goles_casa' => 2, 'goles_fuera' => 1,
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        // El admin acierta el exacto; el miembro falla.
        Pronostico::create([
            'id_usuario' => $admin->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 2, 'goles_visitante_predicho' => 1,
        ]);
        Pronostico::create([
            'id_usuario' => $miembro->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Visitante', 'goles_local_predicho' => 0, 'goles_visitante_predicho' => 1,
        ]);

        return [$liga, $admin->fresh(), $miembro];
    }

    public function test_el_resumen_sale_al_calcular_goleadores_y_no_al_cerrar(): void
    {
        Notification::fake();
        [$liga, $admin, $miembro] = $this->escenario();

        $this->actingAs($admin)->postJson('/api/v1/jornadas/1/cerrar')->assertOk();
        Notification::assertNotSentTo($admin, JornadaCerradaConPuntos::class);

        $this->actingAs($admin)->postJson("/api/v1/admin/cierre-jornada/{$liga->id}/1/goleadores")->assertOk();

        Notification::assertSentTo($admin, JornadaCerradaConPuntos::class, fn ($n) => $n->jornada === 1
            && $n->puntos === $n->resumen['total']
            && $n->resumen['exactos'] === 1
            && $n->resumen['pronosticos'] === 5
            && $n->puntos === 5 + $n->resumen['bonus_pleno']
            && $n->posicion === 1);

        Notification::assertSentTo($miembro, JornadaCerradaConPuntos::class, fn ($n) => $n->puntos === 0 && $n->posicion === 2);
    }

    public function test_recalcular_goleadores_otra_vez_no_repite_el_aviso(): void
    {
        Notification::fake();
        [$liga, $admin] = $this->escenario();

        $this->actingAs($admin)->postJson('/api/v1/jornadas/1/cerrar')->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/admin/cierre-jornada/{$liga->id}/1/goleadores")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/admin/cierre-jornada/{$liga->id}/1/goleadores")->assertOk();

        Notification::assertSentToTimes($admin, JornadaCerradaConPuntos::class, 1);
    }

    public function test_el_correo_lleva_el_desglose_y_la_flecha(): void
    {
        $aviso = new JornadaCerradaConPuntos(9, 12, 1, 'Liga Familia', [
            'jornada' => 9, 'total' => 12, 'pronosticos' => 7, 'partidos' => 10, 'aciertos' => 5, 'exactos' => 2,
            'bonus_pleno' => 0, 'goleadores_puntos' => 4, 'goleadores_elegidos' => 5,
            'goleadores' => [['nombre' => 'Mbappé', 'goles' => 2], ['nombre' => 'Isak', 'goles' => 1]],
            'mostrar_cartas' => true, 'cartas' => 1, 'posicion' => 1, 'cambio_posicion' => 2,
        ]);

        $texto = implode("\n", $aviso->toMail(User::factory()->make())->introLines);

        $this->assertStringContainsString('Tus puntos de esta jornada: **12**', $texto);
        $this->assertStringContainsString('Pronósticos: **7** _(5 de 10 acertados, 2 exactos)_', $texto);
        $this->assertStringContainsString('Goleadores: **4** _(Mbappé 2 goles, Isak 1 gol)_', $texto);
        $this->assertStringContainsString('Cartas: **+1**', $texto);
        $this->assertStringContainsString('subes 2 puestos', $texto);
    }
}
