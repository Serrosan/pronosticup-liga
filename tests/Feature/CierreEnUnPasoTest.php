<?php

namespace Tests\Feature;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\ConfiguracionPuntos;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** "Cerrar todo" en Cierre de jornada: los pasos seguidos, solo con todo en verde. */
class CierreEnUnPasoTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $admin;
    private CalendarioPartido $partido;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        ConfiguracionPuntos::firstOrCreate(['id_liga' => null]);

        $temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'nombre' => 'Liga Familia']);

        $this->admin = User::factory()->create(['liga_activa_id' => $this->liga->id]);
        $this->admin->forceFill(['es_superadmin' => true])->save();
        $this->liga->usuarios()->attach($this->admin->id, ['rol' => 'Admin']);

        $local = Equipo::factory()->create();
        $visitante = Equipo::factory()->create();
        $this->partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 3, 'estado' => 'Jugado',
            'goles_casa' => 1, 'goles_fuera' => 0,
            'id_equipo_local' => $local->id, 'id_equipo_visitante' => $visitante->id,
        ]);

        // Datos de LaLiga completos y que cuadran: alineación + el gol del 1-0.
        $goleador = Jugador::factory()->create();
        DB::table('alineaciones_jugador')->insert(['id_partido' => $this->partido->id, 'id_equipo' => $local->id, 'id_jugador' => $goleador->id, 'titular' => true, 'created_at' => now(), 'updated_at' => now()]);
        EventoPartido::create(['id_partido' => $this->partido->id, 'id_jugador' => $goleador->id, 'id_equipo' => $local->id, 'minuto' => 50, 'tipo_evento' => 'gol']);

        Pronostico::create([
            'id_usuario' => $this->admin->id, 'id_liga' => $this->liga->id, 'id_partido' => $this->partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0,
        ]);
    }

    private function unPaso(): array
    {
        $datos = $this->actingAs($this->admin)->getJson('/api/v1/admin/cierre-jornada?jornada=3')->assertOk()->json('data');

        return collect($datos['ligas'])->firstWhere('id', $this->liga->id)['un_paso'];
    }

    public function test_con_todo_en_verde_cierra_y_calcula_goleadores_de_una_vez(): void
    {
        $unPaso = $this->unPaso();
        $this->assertTrue($unPaso['posible']);
        $this->assertSame(['cerrar', 'goleadores'], array_slice(array_column($unPaso['pendientes'], 'clave'), 0, 2));

        $respuesta = $this->actingAs($this->admin)->postJson("/api/v1/admin/cierre-jornada/{$this->liga->id}/3/todo")
            ->assertOk();
        $this->assertStringStartsWith('Liga Familia, jornada 3: hecho todo (cerrar la jornada, calcular goleadores', $respuesta->json('message'));

        $cierre = CierreJornada::where('id_liga', $this->liga->id)->where('jornada', 3)->first();
        $this->assertTrue((bool) $cierre->cerrada);
        $this->assertNotNull($cierre->goleadores_calculados_en);
        $this->assertTrue(EventoPuntos::where('id_liga', $this->liga->id)->where('jornada', 3)->where('tipo_evento', 'AciertoExacto')->exists());

        $this->assertFalse($this->unPaso()['posible'], 'Ya no queda nada por hacer');
    }

    public function test_si_faltan_datos_no_hace_nada(): void
    {
        AlineacionJugador::where('id_partido', $this->partido->id)->delete();

        $this->assertFalse($this->unPaso()['posible']);
        $this->actingAs($this->admin)->postJson("/api/v1/admin/cierre-jornada/{$this->liga->id}/3/todo")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No se puede cerrar todo de una vez: Faltan datos de LaLiga en 1 partido(s).']);

        $this->assertFalse(CierreJornada::where('id_liga', $this->liga->id)->where('jornada', 3)->where('cerrada', true)->exists());
    }

    public function test_si_los_goles_no_cuadran_no_hace_nada(): void
    {
        $this->partido->update(['goles_casa' => 2]);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/cierre-jornada/{$this->liga->id}/3/todo")->assertStatus(422);
        $this->assertFalse(CierreJornada::where('id_liga', $this->liga->id)->where('jornada', 3)->where('cerrada', true)->exists());
    }

    public function test_si_ya_estaba_cerrada_solo_hace_lo_que_falta(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/admin/cierre-jornada/'.$this->liga->id.'/3/cerrar')->assertOk();

        $this->assertSame('goleadores', array_column($this->unPaso()['pendientes'], 'clave')[0]);
        $this->actingAs($this->admin)->postJson("/api/v1/admin/cierre-jornada/{$this->liga->id}/3/todo")->assertOk();
    }
}
