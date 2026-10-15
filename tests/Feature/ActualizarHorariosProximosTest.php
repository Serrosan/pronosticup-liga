<?php

namespace Tests\Feature;

use App\Console\Commands\ActualizarHorariosProximos;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Temporada;
use App\Services\FootballDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El caso real de octubre de 2026: un aplazado de la jornada 6 que se juega el
 * 21/10 y otro de la 8 sin fecha hacían que la tarea revisara 6, 7 y 8 y nunca
 * la 9, que es la que se jugaba ese fin de semana.
 */
class ActualizarHorariosProximosTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 15:00:00');
        $this->temporada = Temporada::factory()->create();
        $local = Equipo::factory()->create();
        $visitante = Equipo::factory()->create();

        $partido = function (int $jornada, string $estado, string $horario) use ($local, $visitante) {
            CalendarioPartido::factory()->create([
                'id_temporada' => $this->temporada->id, 'id_equipo_local' => $local->id, 'id_equipo_visitante' => $visitante->id,
                'jornada' => $jornada, 'estado' => $estado, 'horario_estimado' => $horario,
                'goles_casa' => $estado === 'Jugado' ? 1 : null, 'goles_fuera' => $estado === 'Jugado' ? 0 : null,
            ]);
        };

        // Jornada 6: jugada, salvo un partido aplazado al 21/10 (descolgado).
        foreach (range(1, 4) as $_) $partido(6, 'Jugado', '2026-09-27 18:00:00');
        $partido(6, 'Programado', '2026-10-21 20:00:00');
        // Jornada 7: jugada entera.
        foreach (range(1, 4) as $_) $partido(7, 'Jugado', '2026-10-04 18:00:00');
        // Jornada 8: jugada, salvo un aplazado todavía sin fecha nueva.
        foreach (range(1, 4) as $_) $partido(8, 'Jugado', '2026-10-11 18:00:00');
        $partido(8, 'Aplazado', '2026-10-11 21:00:00');
        // Jornadas 9 a 12: sin jugar, con horario provisional a las 00:00.
        foreach ([9 => '2026-10-18', 10 => '2026-10-25', 11 => '2026-11-01', 12 => '2026-11-08'] as $j => $dia) {
            foreach (range(1, 4) as $_) $partido($j, 'Programado', "{$dia} 00:00:00");
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_revisa_la_proxima_jornada_de_verdad_y_los_aplazados_pero_no_las_jugadas(): void
    {
        $revisar = app(ActualizarHorariosProximos::class)->jornadasARevisar($this->temporada->id, 3);

        $this->assertSame([6, 8, 9, 10, 11], array_keys($revisar));
        $this->assertSame('próxima', $revisar[9]);
        $this->assertSame('tiene un partido pendiente', $revisar[6]);
    }

    public function test_la_tarea_pide_a_la_api_esas_jornadas(): void
    {
        $pedidas = [];
        $this->mock(FootballDataService::class, function ($mock) use (&$pedidas) {
            $mock->shouldReceive('obtenerJornada')->andReturnUsing(function ($jornada) use (&$pedidas) {
                $pedidas[] = $jornada;
                return [];
            });
        });

        $this->artisan('liga:actualizar-horarios-proximos')
            ->expectsOutputToContain('Revisando jornada 9 (próxima)')
            ->assertSuccessful();

        $this->assertSame([6, 8, 9, 10, 11], $pedidas);
    }
}
