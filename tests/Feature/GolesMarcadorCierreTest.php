<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\Temporada;
use App\Models\User;
use App\Services\CierreJornadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cierre de jornada: avisa si los goles de los eventos no cuadran con el marcador. */
class GolesMarcadorCierreTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporada = Temporada::factory()->create();
    }

    private function partido(int $casa, int $fuera, array $goles): CalendarioPartido
    {
        $local = Equipo::factory()->create(['nombre_corto' => 'Local'.$casa.$fuera]);
        $visitante = Equipo::factory()->create(['nombre_corto' => 'Visit'.$casa.$fuera]);
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'jornada' => 10, 'estado' => 'Jugado',
            'goles_casa' => $casa, 'goles_fuera' => $fuera,
            'id_equipo_local' => $local->id, 'id_equipo_visitante' => $visitante->id,
        ]);

        // Siempre hay algún evento (un cambio), como en un partido real importado.
        $jugador = Jugador::factory()->create();
        EventoPartido::create(['id_partido' => $partido->id, 'id_jugador' => $jugador->id, 'id_equipo' => $local->id, 'minuto' => 60, 'tipo_evento' => 'sustitucion']);

        // [tipo, 'local'|'visitante'] — id_equipo es el equipo al que le SUBE el gol (también en propia).
        foreach ($goles as [$tipo, $lado]) {
            EventoPartido::create([
                'id_partido' => $partido->id, 'id_jugador' => $jugador->id,
                'id_equipo' => $lado === 'local' ? $local->id : $visitante->id,
                'minuto' => 30, 'tipo_evento' => $tipo,
            ]);
        }

        return $partido;
    }

    private function noCuadran(): array
    {
        $estado = app(CierreJornadaService::class)->estado(10, User::factory()->create());

        return collect($estado['partidos']['goles_no_cuadran'])->pluck('id')->all();
    }

    public function test_un_partido_con_los_goles_bien_no_avisa_aunque_haya_gol_en_propia(): void
    {
        $this->partido(2, 1, [['gol', 'local'], ['gol_en_propia', 'local'], ['gol', 'visitante']]);
        $this->partido(0, 0, []);

        $this->assertSame([], $this->noCuadran());
    }

    public function test_avisa_si_falta_un_gol_o_son_de_otro_partido(): void
    {
        $faltaUno = $this->partido(2, 0, [['gol', 'local']]);               // un goleador sin emparejar no se guardó
        $deOtro = $this->partido(1, 0, [['gol', 'visitante']]);             // datos de otro partido

        $this->assertEqualsCanonicalizing([$faltaUno->id, $deOtro->id], $this->noCuadran());

        $fila = collect(app(CierreJornadaService::class)->estado(10, User::factory()->create())['partidos']['goles_no_cuadran'])
            ->firstWhere('id', $faltaUno->id);
        $this->assertSame('2-0', $fila['marcador']);
        $this->assertSame('1-0', $fila['eventos']);
    }

    public function test_un_partido_sin_eventos_no_sale_aqui_sino_en_faltan_datos(): void
    {
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'jornada' => 10, 'estado' => 'Jugado', 'goles_casa' => 3, 'goles_fuera' => 0,
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        $this->assertNotContains($partido->id, $this->noCuadran());
    }
}
