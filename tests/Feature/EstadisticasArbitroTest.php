<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Temporada;
use App\Services\EstadisticasArbitroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadisticasArbitroTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Equipo $local;
    private Equipo $visitante;
    private Jugador $jugador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->local = Equipo::factory()->create();
        $this->visitante = Equipo::factory()->create();
        $this->jugador = Jugador::factory()->create();
    }

    private function partido(?Arbitro $arbitro, string $estado = 'Jugado', array $tarjetas = []): CalendarioPartido
    {
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->local->id,
            'id_equipo_visitante' => $this->visitante->id,
            'id_arbitro' => $arbitro?->id,
            'estado' => $estado,
        ]);

        foreach ($tarjetas as $tipo) {
            DB::table('eventos_partido')->insert([
                'id_partido' => $partido->id, 'id_equipo' => $this->local->id, 'id_jugador' => $this->jugador->id, 'minuto' => 30,
                'tipo_evento' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $partido;
    }

    public function test_suma_las_tarjetas_del_arbitro_en_sus_partidos_con_datos(): void
    {
        $arbitro = Arbitro::factory()->create(['nombre' => 'Jesús', 'apellidos' => 'Gil Manzano']);
        $otro = Arbitro::factory()->create();

        $this->partido($arbitro, 'Jugado', ['tarjeta_amarilla', 'tarjeta_amarilla', 'tarjeta_roja', 'gol']);
        $this->partido($arbitro, 'Jugado', ['tarjeta_amarilla', 'tarjeta_roja', 'tarjeta_roja']);
        $this->partido($arbitro, 'Jugado', ['tarjeta_amarilla', 'gol']);
        $this->partido($arbitro, 'Jugado');                       // jugado pero sin importar: no cuenta
        $this->partido($otro, 'Jugado', ['tarjeta_roja']);        // de otro árbitro
        $proximo = $this->partido($arbitro, 'Programado');

        $this->assertSame(
            ['nombre' => 'Jesús Gil Manzano', 'partidos' => 3, 'amarillas' => 4, 'rojas' => 3, 'partidos_con_roja' => 2],
            app(EstadisticasArbitroService::class)->delPartido($proximo)
        );
    }

    public function test_un_partido_sin_arbitro_asignado_no_devuelve_nada(): void
    {
        $this->assertNull(app(EstadisticasArbitroService::class)->delPartido($this->partido(null, 'Programado')));
    }
}
