<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\GoleadorJornada;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use App\Services\LaJornadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "La jornada en tu liga": se ve lo que ya no se puede cambiar, y antes de
 * eso solo recuentos. Lo importante es que nada se filtre antes de tiempo.
 */
class LaJornadaTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Liga $liga;
    private Equipo $local;
    private Equipo $visitante;
    /** @var User[] */
    private array $usuarios = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => 'ConExtras']);
        $this->local = Equipo::factory()->create(['nombre' => 'Real Betis', 'nombre_corto' => 'Betis']);
        $this->visitante = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);

        foreach (['Ana', 'Bea', 'Carlos', 'Dani', 'Eva'] as $nombre) {
            $usuario = User::factory()->create(['name' => $nombre]);
            $usuario->forceFill(['liga_activa_id' => $this->liga->id])->save();
            $this->liga->usuarios()->attach($usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
            $this->usuarios[] = $usuario;
        }
    }

    private function partido(string $estado, $horario, ?int $golesCasa = null, ?int $golesFuera = null): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->local->id,
            'id_equipo_visitante' => $this->visitante->id,
            'jornada' => 9,
            'estado' => $estado,
            'horario_estimado' => $horario,
            'goles_casa' => $golesCasa,
            'goles_fuera' => $golesFuera,
        ]);
    }

    private function pronosticar(User $usuario, CalendarioPartido $partido, int $local, int $visitante): void
    {
        Pronostico::create([
            'id_usuario' => $usuario->id,
            'id_liga' => $this->liga->id,
            'id_partido' => $partido->id,
            'resultado_1x2' => $local > $visitante ? 'Local' : ($local < $visitante ? 'Visitante' : 'Empate'),
            'goles_local_predicho' => $local,
            'goles_visitante_predicho' => $visitante,
            'enviado_en' => now(),
        ]);
    }

    private function resumen(User $yo): array
    {
        return app(LaJornadaService::class)->resumen($this->liga->fresh(), 9, $yo);
    }

    public function test_antes_de_empezar_solo_se_ven_recuentos_y_lo_tuyo(): void
    {
        $partido = $this->partido('Programado', now()->addDays(3));
        $this->partido('Programado', now()->addDays(3)->addHours(2));
        [$ana, $bea, $carlos] = $this->usuarios;

        $this->pronosticar($ana, $partido, 2, 1);
        $this->pronosticar($bea, $partido, 0, 0);
        $this->pronosticar($carlos, $partido, 1, 3);

        $jugador = Jugador::factory()->create();
        GoleadorJornada::create(['id_usuario' => $bea->id, 'id_liga' => $this->liga->id, 'jornada' => 9, 'id_jugador' => $jugador->id]);

        $resumen = $this->resumen($ana);
        $fila = $resumen['partidos'][0];

        $this->assertFalse($resumen['bloqueada']);
        $this->assertFalse($fila['visible']);
        $this->assertSame(3, $fila['total_pronosticos']);
        $this->assertSame('2-1', $fila['mi_pronostico']);
        $this->assertSame([], $fila['pronosticos']);
        $this->assertNull($fila['reparto']);

        $this->assertFalse($resumen['goleadores']['visible']);
        $this->assertSame(1, $resumen['goleadores']['usuarios_con_seleccion']);
        $this->assertSame([], $resumen['goleadores']['jugadores']);

        $this->assertFalse($resumen['cartas']['visible']);
        $this->assertSame([], $resumen['cartas']['lista']);

        // Nada de lo de Bea o Carlos viaja en la respuesta, en ningún partido.
        foreach ($resumen['partidos'] as $cualquiera) {
            $this->assertSame([], $cualquiera['pronosticos']);
            $this->assertSame([], $cualquiera['contra_todos']);
            $this->assertNull($cualquiera['mas_repetido']);
        }
    }

    public function test_con_el_partido_empezado_se_ve_el_reparto_lo_mas_repetido_y_quien_va_contra_todos(): void
    {
        $partido = $this->partido('Jugado', now()->subDay(), 2, 1);
        [$ana, $bea, $carlos, $dani, $eva] = $this->usuarios;

        $this->pronosticar($ana, $partido, 2, 1);
        $this->pronosticar($bea, $partido, 2, 1);
        $this->pronosticar($carlos, $partido, 1, 0);
        $this->pronosticar($dani, $partido, 0, 0);
        $this->pronosticar($eva, $partido, 0, 2);

        $fila = $this->resumen($ana)['partidos'][0];

        $this->assertTrue($fila['visible']);
        $this->assertSame(['Local' => 3, 'Empate' => 1, 'Visitante' => 1], $fila['reparto']);
        $this->assertSame(['marcador' => '2-1', 'veces' => 2], $fila['mas_repetido']);
        $this->assertEqualsCanonicalizing(['Dani', 'Eva'], array_column($fila['contra_todos'], 'usuario'));
        $this->assertCount(5, $fila['pronosticos']);
        $this->assertTrue(collect($fila['pronosticos'])->firstWhere('id_usuario', $ana->id)['soy_yo']);
    }

    public function test_un_aplazado_no_se_ensena_aunque_la_jornada_este_bloqueada(): void
    {
        $this->partido('Jugado', now()->subDays(2), 1, 1);
        $aplazado = $this->partido('Aplazado', now()->subDays(2));
        $this->pronosticar($this->usuarios[1], $aplazado, 3, 0);

        $resumen = $this->resumen($this->usuarios[0]);
        $fila = collect($resumen['partidos'])->firstWhere('id', $aplazado->id);

        $this->assertTrue($resumen['bloqueada']);
        $this->assertFalse($fila['visible']);
        $this->assertSame(1, $fila['total_pronosticos']);
        $this->assertSame([], $fila['pronosticos']);
    }

    public function test_con_la_jornada_empezada_los_goleadores_salen_agrupados_con_sus_goles(): void
    {
        $partido = $this->partido('Jugado', now()->subDay(), 2, 0);
        [$ana, $bea, $carlos] = $this->usuarios;
        $delantero = Jugador::factory()->create(['nombre_camiseta' => 'Delantero']);
        $extremo = Jugador::factory()->create(['nombre_camiseta' => 'Extremo']);

        foreach ([[$ana, $delantero], [$bea, $delantero], [$carlos, $extremo]] as [$usuario, $jugador]) {
            GoleadorJornada::create(['id_usuario' => $usuario->id, 'id_liga' => $this->liga->id, 'jornada' => 9, 'id_jugador' => $jugador->id]);
        }

        foreach (['gol', 'gol', 'gol_en_propia'] as $tipo) {
            DB::table('eventos_partido')->insert([
                'id_partido' => $partido->id, 'id_jugador' => $delantero->id, 'id_equipo' => $this->local->id,
                'minuto' => 10, 'tipo_evento' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $goleadores = $this->resumen($ana)['goleadores'];

        $this->assertTrue($goleadores['visible']);
        $this->assertSame('Delantero', $goleadores['jugadores'][0]['nombre']);
        $this->assertSame(2, $goleadores['jugadores'][0]['goles']);
        $this->assertCount(2, $goleadores['jugadores'][0]['elegido_por']);
        $this->assertSame('Extremo', $goleadores['jugadores'][1]['nombre']);
        $this->assertSame(0, $goleadores['jugadores'][1]['goles']);
    }

    public function test_la_ruta_pide_liga_activa_y_responde_con_la_jornada(): void
    {
        $this->partido('Programado', now()->addDays(3));

        $sinLiga = User::factory()->create();
        Sanctum::actingAs($sinLiga);
        $this->getJson('/api/v1/jornadas/9/la-jornada')->assertStatus(409);

        Sanctum::actingAs($this->usuarios[0]);
        $this->getJson('/api/v1/jornadas/9/la-jornada')
            ->assertOk()
            ->assertJsonPath('data.jornada', 9)
            ->assertJsonPath('data.miembros', 5)
            ->assertJsonPath('data.con_cartas', true)
            ->assertJsonPath('data.cartas.visible', false);
    }
}
