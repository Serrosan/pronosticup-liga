<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La ayuda para descubrir goleadores: partidos de la jornada con lo que marca
 * y encaja cada equipo, y la plantilla con posición y dorsal.
 */
class AyudaGoleadoresTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private User $usuario;
    private Equipo $betis;
    private Equipo $sevilla;
    private Equipo $rayo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => 'ConExtras']);
        $this->usuario = User::factory()->create();
        $this->usuario->forceFill(['liga_activa_id' => $liga->id])->save();
        $liga->usuarios()->attach($this->usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);

        $this->betis = Equipo::factory()->create(['nombre' => 'Real Betis', 'nombre_corto' => 'Betis']);
        $this->sevilla = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $this->rayo = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);
    }

    private function partido(int $jornada, Equipo $local, Equipo $visitante, string $estado, ?int $casa = null, ?int $fuera = null): void
    {
        CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'jornada' => $jornada,
            'id_equipo_local' => $local->id, 'id_equipo_visitante' => $visitante->id,
            'estado' => $estado, 'horario_estimado' => now()->addDays($jornada),
            'goles_casa' => $casa, 'goles_fuera' => $fuera,
        ]);
    }

    public function test_partidos_de_la_jornada_con_goles_marcados_y_encajados(): void
    {
        $this->partido(1, $this->betis, $this->sevilla, 'Jugado', 3, 1);
        $this->partido(2, $this->rayo, $this->betis, 'Jugado', 2, 2);
        $this->partido(9, $this->sevilla, $this->betis, 'Programado');
        $this->partido(9, $this->rayo, $this->rayo, 'Aplazado');

        Sanctum::actingAs($this->usuario);
        $partidos = $this->getJson('/api/v1/jornadas/9/goleadores-ayuda')->assertOk()->json('data.partidos');

        $this->assertCount(2, $partidos);
        $this->assertSame('Sevilla', $partidos[0]['local']['nombre']);
        $this->assertSame(['jugados' => 1, 'marcados' => 1, 'encajados' => 3],
            array_intersect_key($partidos[0]['local'], array_flip(['jugados', 'marcados', 'encajados'])));
        $this->assertSame(['jugados' => 2, 'marcados' => 5, 'encajados' => 3],
            array_intersect_key($partidos[0]['visitante'], array_flip(['jugados', 'marcados', 'encajados'])));
        $this->assertSame('Aplazado', $partidos[1]['estado']);
    }

    public function test_sin_liga_activa_avisa(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/jornadas/9/goleadores-ayuda')->assertStatus(409);
    }

    public function test_los_buscables_traen_equipo_posicion_y_dorsal(): void
    {
        $jugador = Jugador::factory()->create(['nombre_camiseta' => 'Abde', 'posicion' => 'Delantero', 'dado_de_baja_en' => null]);
        PlantillaTemporada::create([
            'id_jugador' => $jugador->id, 'id_equipo' => $this->betis->id, 'id_temporada' => $this->temporada->id,
            'dorsal' => 10, 'fecha_salida' => null,
        ]);

        Sanctum::actingAs($this->usuario);
        $fila = collect($this->getJson('/api/v1/jugadores-buscables')->assertOk()->json('data'))->firstWhere('id', $jugador->id);

        $this->assertSame('Abde', $fila['nombre']);
        $this->assertSame('Betis', $fila['equipo']);
        $this->assertSame($this->betis->id, $fila['id_equipo']);
        $this->assertSame('Delantero', $fila['posicion']);
        $this->assertSame(10, (int) $fila['dorsal']);
    }
}
