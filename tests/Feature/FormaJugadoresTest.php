<?php

namespace Tests\Feature;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Temporada;
use App\Services\FormaJugadoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La ayuda para elegir goleadores: goles de la temporada y titularidades en
 * los últimos 5 partidos del equipo, sacados de lo que importa el scraper.
 */
class FormaJugadoresTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Equipo $sevilla;
    private Equipo $rayo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->sevilla = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $this->rayo = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);
    }

    private function partido(int $jornada, string $estado = 'Jugado'): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->sevilla->id,
            'id_equipo_visitante' => $this->rayo->id,
            'jornada' => $jornada,
            'estado' => $estado,
            'horario_estimado' => now()->subDays(60 - $jornada * 7),
            'goles_casa' => $estado === 'Jugado' ? 1 : null,
            'goles_fuera' => $estado === 'Jugado' ? 0 : null,
        ]);
    }

    private function alinear(CalendarioPartido $partido, Jugador $jugador, Equipo $equipo, bool $titular): void
    {
        AlineacionJugador::create([
            'id_partido' => $partido->id, 'id_equipo' => $equipo->id, 'id_jugador' => $jugador->id,
            'dorsal' => 9, 'titular' => $titular,
        ]);
    }

    private function gol(CalendarioPartido $partido, Jugador $jugador, Equipo $equipo, string $tipo = 'gol'): void
    {
        DB::table('eventos_partido')->insert([
            'id_partido' => $partido->id, 'id_jugador' => $jugador->id, 'id_equipo' => $equipo->id,
            'minuto' => 10, 'tipo_evento' => $tipo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cuenta_goles_de_la_temporada_y_titularidades_de_los_ultimos_cinco(): void
    {
        $delantero = Jugador::factory()->create();
        $suplente = Jugador::factory()->create();

        // 7 partidos jugados: el delantero es titular en todos menos en el más reciente.
        foreach (range(1, 7) as $jornada) {
            $partido = $this->partido($jornada);
            $this->alinear($partido, $delantero, $this->sevilla, $jornada !== 7);
            $this->alinear($partido, $suplente, $this->sevilla, false);
            if (in_array($jornada, [1, 2, 6])) {
                $this->gol($partido, $delantero, $this->sevilla);
            }
        }

        $forma = app(FormaJugadoresService::class)->deTemporada($this->temporada->id);

        // Últimos 5 = jornadas 3 a 7: titular en 4 (la 7 fue suplente), convocado en las 5.
        $this->assertSame(
            ['goles' => 3, 'goles_recientes' => 1, 'titular' => 4, 'convocado' => 5, 'de' => 5],
            $forma[$delantero->id]
        );
        $this->assertSame(0, $forma[$suplente->id]['titular']);
        $this->assertSame(5, $forma[$suplente->id]['convocado']);
    }

    public function test_los_goles_en_propia_puerta_y_los_partidos_sin_jugar_no_cuentan(): void
    {
        $defensa = Jugador::factory()->create();

        $jugado = $this->partido(1);
        $this->alinear($jugado, $defensa, $this->rayo, true);
        $this->gol($jugado, $defensa, $this->sevilla, 'gol_en_propia');

        $porJugar = $this->partido(2, 'Programado');
        $this->alinear($porJugar, $defensa, $this->rayo, true);

        $forma = app(FormaJugadoresService::class)->deTemporada($this->temporada->id);

        $this->assertSame(
            ['goles' => 0, 'goles_recientes' => 0, 'titular' => 1, 'convocado' => 1, 'de' => 1],
            $forma[$defensa->id]
        );
    }

    public function test_sin_partidos_jugados_no_hay_datos(): void
    {
        $this->partido(1, 'Programado');

        $this->assertSame([], app(FormaJugadoresService::class)->deTemporada($this->temporada->id));
    }
}
