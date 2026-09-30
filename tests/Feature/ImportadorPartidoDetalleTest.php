<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Services\ImportadorPartidoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los casos reales encontrados el día que se construyó e integró este
 * importador contra partidos de verdad de LaLiga.com (Sevilla-Rayo J1,
 * Atlético-Real Madrid J7) — cada test aquí corresponde a un fallo real que
 * se vio con datos reales, no a un caso hipotético.
 */
class ImportadorPartidoDetalleTest extends TestCase
{
    use RefreshDatabase;

    private ImportadorPartidoDetalle $importador;
    private Temporada $temporada;
    private Equipo $local;
    private Equipo $visitante;
    private CalendarioPartido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importador = app(ImportadorPartidoDetalle::class);

        $this->temporada = Temporada::factory()->create();
        $this->local = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $this->visitante = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);

        $this->partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->local->id,
            'id_equipo_visitante' => $this->visitante->id,
            'jornada' => 1,
            'horario_estimado' => '2026-08-15 19:30:00',
        ]);
    }

    /** Crea un jugador con ficha en plantilla_temporada para el equipo indicado. */
    private function crearJugador(Equipo $equipo, string $nombre, string $apellidos, ?string $nombreCamiseta = null, ?string $fechaSalida = null): Jugador
    {
        $jugador = Jugador::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos, 'nombre_camiseta' => $nombreCamiseta]);

        PlantillaTemporada::create([
            'id_jugador' => $jugador->id,
            'id_equipo' => $equipo->id,
            'id_temporada' => $this->temporada->id,
            'fecha_incorporacion' => null,
            'fecha_salida' => $fechaSalida,
        ]);

        return $jugador;
    }

    private function persona(string $nombre, string $apellidos, ?string $nickname = null): array
    {
        return ['name' => "{$nombre} {$apellidos}", 'nickname' => $nickname ?? $nombre, 'firstname' => $nombre, 'lastname' => $apellidos];
    }

    private function payloadBase(array $overrides = []): array
    {
        return array_merge([
            'equipo_local' => 'Sevilla FC',
            'equipo_visitante' => 'Rayo Vallecano',
            'id_laliga_local' => 17,
            'id_laliga_visitante' => 14,
            'jornada' => 1,
            'formacion_local' => '4231',
            'formacion_visitante' => '4231',
            'lineups' => ['home' => ['starts' => [], 'subs' => []], 'away' => ['starts' => [], 'subs' => []]],
            'stats' => ['home' => [], 'away' => []],
            'events' => [],
        ], $overrides);
    }

    public function test_importa_alineacion_estadisticas_y_un_gol_correctamente(): void
    {
        $goleador = $this->crearJugador($this->local, 'Isaac', 'Romero', 'Isaac');

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [['shirt_number' => 9, 'status' => 'start', 'person' => $this->persona('Isaac', 'Romero', 'Isaac')]], 'subs' => []],
                'away' => ['starts' => [], 'subs' => []],
            ],
            'stats' => [
                'home' => ['possession_percentage' => 55.5, 'total_scoring_att' => 10, 'goals' => 2],
                'away' => [],
            ],
            'events' => [
                ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Isaac', 'Romero', 'Isaac')], 'clock' => '23'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $goleador->id, 'titular' => true, 'dorsal' => 9]);
        $this->assertDatabaseHas('estadisticas_partido', ['id_partido' => $this->partido->id, 'id_equipo' => $this->local->id, 'remates' => 10, 'efectividad' => 20.0]);
        $this->assertDatabaseHas('eventos_partido', ['id_partido' => $this->partido->id, 'id_jugador' => $goleador->id, 'tipo_evento' => 'gol', 'minuto' => 23]);
    }

    public function test_gol_en_propia_cuenta_para_el_equipo_contrario(): void
    {
        $jugadorLocal = $this->crearJugador($this->local, 'Matias', 'Perez', 'Matias');

        $payload = $this->payloadBase([
            'events' => [
                ['match_event_kind' => ['id' => 3, 'name' => 'Own', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Matias', 'Perez', 'Matias')], 'clock' => '25'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseHas('eventos_partido', [
            'id_partido' => $this->partido->id,
            'id_jugador' => $jugadorLocal->id,
            'tipo_evento' => 'gol_en_propia',
            'id_equipo' => $this->visitante->id, // invertido — el gol cuenta para el contrario
        ]);
    }

    public function test_doble_amarilla_genera_tarjeta_amarilla_y_roja(): void
    {
        $jugador = $this->crearJugador($this->local, 'Jon', 'Guridi', 'Guridi');

        $payload = $this->payloadBase([
            'events' => [
                ['match_event_kind' => ['id' => 11, 'name' => 'Second Yellow', 'collection' => 'booking'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Jon', 'Guridi', 'Guridi')], 'clock' => '80'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(1, EventoPartido::where('tipo_evento', 'tarjeta_amarilla')->where('id_jugador', $jugador->id)->count());
        $this->assertSame(1, EventoPartido::where('tipo_evento', 'tarjeta_roja')->where('id_jugador', $jugador->id)->count());
    }

    public function test_encuentra_jugador_que_se_fue_del_equipo_despues_del_partido(): void
    {
        // Caso real: Víctor García (Levante), fecha_salida posterior al partido que
        // se está importando — debe seguir emparejando, porque SÍ estaba en el
        // equipo el día del partido, aunque ya no esté hoy.
        $jugador = $this->crearJugador($this->local, 'Victor', 'Garcia', 'Victor', fechaSalida: '2026-09-01');

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [['shirt_number' => 3, 'status' => 'start', 'person' => $this->persona('Victor', 'Garcia', 'Victor')]], 'subs' => []],
                'away' => ['starts' => [], 'subs' => []],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $jugador->id]);
    }

    public function test_colision_de_emparejamiento_se_avisa_en_vez_de_sobrescribir_en_silencio(): void
    {
        // Caso real: Ibrahima Konaté (titular) y Brahim Díaz (suplente), ambos del
        // Real Madrid, ambos SIN nombre_camiseta — igual que en producción. El
        // algoritmo, al buscar "Brahim", encuentra que "brahim" está contenido
        // dentro de "ibrahima konate" y los confunde. El orden de creación
        // importa aquí (Konaté primero) para reproducir el mismo orden de
        // procesamiento que tuvo el caso real.
        $konate = $this->crearJugador($this->visitante, 'Ibrahima', 'Konate');
        $brahim = $this->crearJugador($this->visitante, 'Brahim', 'Diaz');

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [], 'subs' => []],
                'away' => [
                    'starts' => [['shirt_number' => 4, 'status' => 'start', 'person' => $this->persona('Ibrahima', 'Konate', 'Konate')]],
                    'subs' => [['shirt_number' => 21, 'status' => 'sub', 'person' => $this->persona('Brahim', 'Diaz', 'Brahim')]],
                ],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        // Konaté (el primero procesado) se queda bien guardado.
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $konate->id, 'titular' => true]);
        // Brahim NO se guarda — pero tampoco se pierde en silencio: hay un aviso.
        $this->assertDatabaseMissing('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $brahim->id]);
        $huboAvisoDeColision = collect($resultado['avisos'])->contains(fn ($a) => str_contains($a, 'Colisión'));
        $this->assertTrue($huboAvisoDeColision, 'Se esperaba un aviso de colisión de emparejamiento.');
    }

    public function test_jugador_sin_emparejar_genera_aviso_sin_romper_el_resto(): void
    {
        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [['shirt_number' => 9, 'status' => 'start', 'person' => $this->persona('Desconocido', 'Total', 'Desconocido')]], 'subs' => []],
                'away' => ['starts' => [], 'subs' => []],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertNotEmpty($resultado['avisos']);
        $this->assertDatabaseCount('alineaciones_jugador', 0);
    }

    public function test_equipo_sin_emparejar_devuelve_resultado_no_ok(): void
    {
        $payload = $this->payloadBase(['equipo_local' => 'Equipo Que No Existe En La Base De Datos']);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertFalse($resultado['ok']);
        $this->assertNotEmpty($resultado['avisos']);
    }

    public function test_tipo_de_evento_desconocido_genera_aviso_sin_romper_el_resto(): void
    {
        $this->crearJugador($this->local, 'Test', 'Jugador', 'Test');

        $payload = $this->payloadBase([
            'events' => [
                ['match_event_kind' => ['id' => 99, 'name' => 'TipoRaroInventado', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Test', 'Jugador', 'Test')], 'clock' => '10'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseCount('eventos_partido', 0);
        $this->assertNotEmpty($resultado['avisos']);
    }
}