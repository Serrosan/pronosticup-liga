<?php

namespace Tests\Feature;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Services\CalidadDatosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalidadDatosTest extends TestCase
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

    private function ficha(Jugador $jugador, Equipo $equipo, ?int $dorsal = null, ?string $desde = null, ?string $hasta = null): PlantillaTemporada
    {
        return PlantillaTemporada::create([
            'id_jugador' => $jugador->id, 'id_equipo' => $equipo->id, 'id_temporada' => $this->temporada->id,
            'dorsal' => $dorsal, 'fecha_incorporacion' => $desde, 'fecha_salida' => $hasta,
        ]);
    }

    private function revision(string $clave): array
    {
        return collect(app(CalidadDatosService::class)->revisar()['revisiones'])->firstWhere('clave', $clave);
    }

    public function test_detecta_dos_fichas_activas_y_dorsales_repetidos(): void
    {
        $doble = Jugador::factory()->create(['nombre' => 'Isaac', 'apellidos' => 'Romero']);
        $this->ficha($doble, $this->sevilla, 7);
        $this->ficha($doble, $this->rayo, 9);

        $otro = Jugador::factory()->create(['nombre' => 'Juanlu', 'apellidos' => 'Sánchez']);
        $this->ficha($otro, $this->sevilla, 7);

        $dobles = $this->revision('dos_fichas_activas');
        $this->assertSame(1, $dobles['total']);
        $this->assertSame($doble->id, $dobles['filas'][0]['id_jugador']);

        $this->assertSame(2, $this->revision('dorsal_repetido')['total']);
    }

    public function test_detecta_alineado_antes_de_su_fecha_de_incorporacion(): void
    {
        // El caso de la semana pasada: ficha que empieza después de sus primeros partidos.
        $iker = Jugador::factory()->create(['nombre' => 'Iker', 'apellidos' => 'Munoz']);
        $this->ficha($iker, $this->sevilla, 34, '2026-10-04');

        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'id_equipo_local' => $this->sevilla->id, 'id_equipo_visitante' => $this->rayo->id,
            'jornada' => 1, 'horario_estimado' => '2026-08-15 19:30:00',
        ]);
        AlineacionJugador::create(['id_partido' => $partido->id, 'id_equipo' => $this->sevilla->id, 'id_jugador' => $iker->id, 'dorsal' => 34, 'titular' => true]);

        $revision = $this->revision('alineado_sin_ficha');

        $this->assertSame(1, $revision['total']);
        $this->assertSame('Sevilla', $revision['filas'][0]['equipo']);
        $this->assertStringContainsString('jornada 1', $revision['filas'][0]['detalle']);
    }

    public function test_fichas_que_se_pisan_si_y_un_traspaso_bien_hecho_no(): void
    {
        $mal = Jugador::factory()->create(['nombre' => 'Pepe', 'apellidos' => 'Mal']);
        $this->ficha($mal, $this->sevilla, 3, null, '2026-09-10');
        $this->ficha($mal, $this->rayo, 3, '2026-09-01');

        $bien = Jugador::factory()->create(['nombre' => 'Luis', 'apellidos' => 'Bien']);
        $this->ficha($bien, $this->sevilla, 4, null, '2026-09-01');
        $this->ficha($bien, $this->rayo, 4, '2026-09-01');

        $revision = $this->revision('fechas_solapadas');

        $this->assertSame(1, $revision['total']);
        $this->assertSame($mal->id, $revision['filas'][0]['id_jugador']);
    }

    public function test_sin_problemas_todo_sale_a_cero(): void
    {
        $bien = Jugador::factory()->create(['nombre' => 'Luis', 'apellidos' => 'Bien', 'nombre_camiseta' => 'Luis', 'foto_url' => 'x.png', 'nacionalidad' => 'España', 'fecha_nacimiento' => '2000-01-01']);
        $this->ficha($bien, $this->sevilla, 4);

        foreach (app(CalidadDatosService::class)->revisar()['revisiones'] as $revision) {
            $this->assertSame(0, $revision['total'], $revision['clave']);
        }
    }
}
