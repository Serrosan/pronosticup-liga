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
 * Atlético-Real Madrid J7) y los que salieron al importar las 7 primeras
 * jornadas en producción — cada test aquí corresponde a un fallo real que
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
    private function crearJugador(Equipo $equipo, string $nombre, string $apellidos, ?string $nombreCamiseta = null, ?string $fechaSalida = null, ?int $dorsal = null): Jugador
    {
        $jugador = Jugador::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos, 'nombre_camiseta' => $nombreCamiseta]);

        PlantillaTemporada::create([
            'id_jugador' => $jugador->id,
            'id_equipo' => $equipo->id,
            'id_temporada' => $this->temporada->id,
            'dorsal' => $dorsal,
            'fecha_incorporacion' => null,
            'fecha_salida' => $fechaSalida,
        ]);

        return $jugador;
    }

    private function persona(string $nombre, string $apellidos, ?string $nickname = null): array
    {
        return ['name' => "{$nombre} {$apellidos}", 'nickname' => $nickname ?? $nombre, 'firstname' => $nombre, 'lastname' => $apellidos];
    }

    /** Una línea de alineación tal como la manda LaLiga. */
    private function entrada(int $dorsal, array $persona, string $estado = 'start'): array
    {
        return ['shirt_number' => $dorsal, 'status' => $estado, 'person' => $persona];
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

    /** Payload con solo la alineación titular del equipo local. */
    private function payloadConTitularesLocales(array $titulares, array $overrides = []): array
    {
        return $this->payloadBase(array_merge([
            'lineups' => [
                'home' => ['starts' => $titulares, 'subs' => []],
                'away' => ['starts' => [], 'subs' => []],
            ],
        ], $overrides));
    }

    private function hayAvisoCon(array $avisos, string $texto): bool
    {
        return collect($avisos)->contains(fn ($aviso) => str_contains($aviso, $texto));
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

    public function test_brahim_y_konate_se_guardan_los_dos_sin_confundirse(): void
    {
        // Caso real: Ibrahima Konaté (titular) y Brahim Díaz (suplente), ambos del
        // Real Madrid, ambos SIN nombre_camiseta — igual que en producción. Antes
        // "brahim" se encontraba como trozo dentro de "ibrahima konate" y Brahim
        // se quedaba sin guardar. Ahora solo valen palabras enteras: cada uno el suyo.
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
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $konate->id, 'titular' => true]);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $brahim->id, 'titular' => false]);
    }

    public function test_si_el_parecido_no_esta_en_la_base_de_datos_no_se_le_asigna_otro_jugador(): void
    {
        // Brahim no está dado de alta: NO debe quedarse con la ficha de Konaté.
        $konate = $this->crearJugador($this->visitante, 'Ibrahima', 'Konate');

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [], 'subs' => []],
                'away' => [
                    'starts' => [$this->entrada(21, $this->persona('Brahim', 'Diaz', 'Brahim')), $this->entrada(4, $this->persona('Ibrahima', 'Konate', 'Konate'))],
                    'subs' => [],
                ],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseCount('alineaciones_jugador', 1);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $konate->id, 'dorsal' => 4]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'Brahim Diaz'));
    }

    public function test_mismo_apellido_y_distinto_nombre_de_pila_no_se_asigna(): void
    {
        // Caso real (Racing, J1): 'Hugo Martín' se guardaba como Andrés Martín.
        $andres = $this->crearJugador($this->local, 'Andrés', 'Martín');

        $payload = $this->payloadConTitularesLocales([$this->entrada(30, $this->persona('Hugo', 'Martín', 'Hugo Martín'))]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseMissing('alineaciones_jugador', ['id_jugador' => $andres->id]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'Hugo Martín'));
    }

    public function test_el_dorsal_confirma_al_jugador_cuando_el_nombre_no_coincide_del_todo(): void
    {
        // Caso real (Rayo, J1): LaLiga dice "Isi Palazón" y aquí es "Isaac Palazón".
        // Mismo equipo + mismo dorsal + mismo apellido: es él.
        $isi = $this->crearJugador($this->local, 'Isaac', 'Palazón', dorsal: 7);

        $payload = $this->payloadConTitularesLocales([$this->entrada(7, $this->persona('Isi', 'Palazón', 'Isi'))]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $isi->id, 'dorsal' => 7]);
    }

    public function test_sin_dorsal_ni_nombre_claro_no_se_asigna_y_el_aviso_dice_quien_podria_ser(): void
    {
        // El mismo caso sin dorsal en la plantilla: no hay segunda pista, así que
        // no se asigna — pero el aviso apunta al candidato para arreglarlo rápido.
        $isi = $this->crearJugador($this->local, 'Isaac', 'Palazón');

        $payload = $this->payloadConTitularesLocales([$this->entrada(7, $this->persona('Isi', 'Palazón', 'Isi'))]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseMissing('alineaciones_jugador', ['id_jugador' => $isi->id]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'podría ser Isaac Palazón'));
    }

    public function test_hermanos_con_el_mismo_apodo_y_el_gol_va_al_que_marco(): void
    {
        // Caso real (Athletic, J1): Iñaki y Nico Williams resolvían al mismo. El
        // apodo "Williams" vale para los dos; el gol es de Nico y debe ser de Nico.
        $inaki = $this->crearJugador($this->local, 'Iñaki', 'Williams');
        $nico = $this->crearJugador($this->local, 'Nico', 'Williams');

        $payload = $this->payloadConTitularesLocales(
            [
                $this->entrada(9, $this->persona('Iñaki', 'Williams', 'Williams')),
                $this->entrada(10, $this->persona('Nico', 'Williams', 'Williams')),
            ],
            ['events' => [
                ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Nico', 'Williams', 'Williams')], 'clock' => '61'],
            ]]
        );

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $inaki->id, 'dorsal' => 9]);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $nico->id, 'dorsal' => 10]);
        $this->assertDatabaseHas('eventos_partido', ['id_partido' => $this->partido->id, 'id_jugador' => $nico->id, 'tipo_evento' => 'gol', 'minuto' => 61]);
        $this->assertDatabaseMissing('eventos_partido', ['id_jugador' => $inaki->id]);
    }

    public function test_gol_de_un_jugador_no_emparejado_en_la_alineacion_avisa_y_no_se_regala_a_otro(): void
    {
        $andres = $this->crearJugador($this->local, 'Andrés', 'Martín');

        $payload = $this->payloadConTitularesLocales(
            [$this->entrada(30, $this->persona('Hugo', 'Martín', 'Hugo Martín'))],
            ['events' => [
                ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Hugo', 'Martín', 'Hugo Martín')], 'clock' => '12'],
            ]]
        );

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseCount('eventos_partido', 0);
        $this->assertDatabaseMissing('eventos_partido', ['id_jugador' => $andres->id]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'Goleador sin emparejar'));
    }

    public function test_tarjeta_a_un_entrenador_no_genera_aviso(): void
    {
        // Caso real (Getafe, J1): "Jugador amonestado sin emparejar: José Bordalás".
        $entrenador = $this->persona('José', 'Bordalás', 'Bordalás');

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['manager' => [['id' => 1, 'position' => 0, 'person' => $entrenador]], 'starts' => [], 'subs' => []],
                'away' => ['starts' => [], 'subs' => []],
            ],
            'events' => [
                ['match_event_kind' => ['id' => 10, 'name' => 'Yellow', 'collection' => 'booking'], 'lineup' => ['team' => ['id' => 17], 'person' => $entrenador], 'clock' => '44'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseCount('eventos_partido', 0);
    }

    public function test_penalti_fallado_se_ignora_sin_aviso(): void
    {
        $this->crearJugador($this->local, 'Oihan', 'Sancet');

        $payload = $this->payloadBase([
            'events' => [
                ['match_event_kind' => ['id' => 6, 'name' => 'Saved', 'collection' => 'missedPenalty'], 'lineup' => ['team' => ['id' => 17], 'person' => $this->persona('Oihan', 'Sancet', 'O. Sancet')], 'clock' => '70'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseCount('eventos_partido', 0);
    }

    public function test_reimportar_reemplaza_la_alineacion_anterior(): void
    {
        // Si una importación anterior guardó a quien no era, al reimportar esa
        // fila tiene que desaparecer, no quedarse junto a la buena.
        $primero = $this->crearJugador($this->local, 'Isaac', 'Romero');
        $segundo = $this->crearJugador($this->local, 'Jon', 'Guridi');

        $this->importador->importar($this->payloadConTitularesLocales([$this->entrada(9, $this->persona('Isaac', 'Romero'))]), $this->temporada->id);
        $this->importador->importar($this->payloadConTitularesLocales([$this->entrada(8, $this->persona('Jon', 'Guridi'))]), $this->temporada->id);

        $this->assertDatabaseMissing('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $primero->id]);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $segundo->id]);
    }

    public function test_alineacion_vacia_no_borra_la_que_ya_habia(): void
    {
        // LaLiga a veces tarda en publicar la alineación: reimportar con ella vacía
        // no debe dejar el partido sin la que ya estaba guardada.
        $jugador = $this->crearJugador($this->local, 'Isaac', 'Romero');

        $this->importador->importar($this->payloadConTitularesLocales([$this->entrada(9, $this->persona('Isaac', 'Romero'))]), $this->temporada->id);
        $this->importador->importar($this->payloadBase(), $this->temporada->id);

        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $jugador->id]);
    }

    public function test_aviso_distingue_jugador_que_existe_pero_no_esta_en_la_plantilla_del_equipo(): void
    {
        // Está dado de alta, pero en la plantilla de OTRO equipo: el aviso debe
        // mandar a revisar plantilla_temporada, no a crear el jugador.
        $this->crearJugador($this->visitante, 'Pablo', 'Maffeo');

        $payload = $this->payloadConTitularesLocales([$this->entrada(2, $this->persona('Pablo', 'Maffeo', 'Maffeo'))]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseCount('alineaciones_jugador', 0);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'no figura en la plantilla de Sevilla'));
    }

    public function test_mismo_dorsal_y_una_palabra_en_comun_basta_aunque_el_apellido_no_cuadre(): void
    {
        // Caso real (Celta-Racing, J7): LaLiga manda el nombre legal "Moriba
        // Kourouma Kourouma" y aquí es "Ilaix Moriba", los dos con el 6. Su gol
        // se quedaba sin guardar.
        $moriba = $this->crearJugador($this->local, 'Ilaix', 'Moriba', dorsal: 6);
        $persona = ['name' => 'Moriba Kourouma Kourouma', 'nickname' => 'Moriba Kourouma Kourouma', 'firstname' => 'Moriba', 'lastname' => 'Kourouma Kourouma'];

        $payload = $this->payloadConTitularesLocales(
            [$this->entrada(6, $persona)],
            ['events' => [
                ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $persona], 'clock' => '43'],
            ]]
        );

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $moriba->id, 'dorsal' => 6]);
        $this->assertDatabaseHas('eventos_partido', ['id_partido' => $this->partido->id, 'id_jugador' => $moriba->id, 'tipo_evento' => 'gol', 'minuto' => 43]);
    }

    public function test_mismo_dorsal_sin_ninguna_palabra_en_comun_no_se_asigna(): void
    {
        // Caso real (Getafe): el 31 de la plantilla es Bekhoucha y LaLiga trae a
        // Hamdoune con el 31. El dorsal solo no basta.
        $bekhoucha = $this->crearJugador($this->local, 'Ismael', 'Bekhoucha', dorsal: 31);

        $payload = $this->payloadConTitularesLocales([$this->entrada(31, $this->persona('Mohamed', 'Hamdoune', 'Hamdoune'))]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseMissing('alineaciones_jugador', ['id_jugador' => $bekhoucha->id]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'Mohamed Hamdoune'));
    }

    public function test_apellidos_que_se_parecen_no_son_el_mismo_apellido(): void
    {
        // Hernández y Fernández se diferencian en una letra: no es una errata.
        $toni = $this->crearJugador($this->local, 'Toni', 'Fernández');
        $rodri = ['name' => 'Rodrigo Hernández Cascante', 'nickname' => 'Rodri', 'firstname' => 'Rodrigo', 'lastname' => 'Hernández Cascante'];

        $payload = $this->payloadConTitularesLocales([$this->entrada(16, $rodri)]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertDatabaseMissing('alineaciones_jugador', ['id_jugador' => $toni->id]);
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'no está dado de alta'));
    }

    public function test_apodo_corto_no_se_busca_como_trozo_dentro_de_otro_nombre(): void
    {
        // Caso real (Athletic-Sevilla, J2): el gol de "Oso" se guardaba a nombre de
        // Fábio Cardoso, porque "oso" está dentro de "cardoso".
        $cardoso = $this->crearJugador($this->visitante, 'Fábio', 'Cardoso', dorsal: 15);
        $oso = $this->crearJugador($this->visitante, 'Oso', '', dorsal: 19);
        $persona = ['name' => 'Joaquín Martínez Gauna', 'nickname' => 'Oso', 'firstname' => 'Joaquín', 'lastname' => 'Martínez Gauna'];

        $payload = $this->payloadBase([
            'lineups' => [
                'home' => ['starts' => [], 'subs' => []],
                'away' => ['starts' => [$this->entrada(19, $persona)], 'subs' => []],
            ],
            'events' => [
                ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 14], 'person' => $persona], 'clock' => '70'],
            ],
        ]);

        $resultado = $this->importador->importar($payload, $this->temporada->id);

        $this->assertTrue($resultado['ok']);
        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('eventos_partido', ['id_partido' => $this->partido->id, 'id_jugador' => $oso->id, 'tipo_evento' => 'gol']);
        $this->assertDatabaseMissing('eventos_partido', ['id_jugador' => $cardoso->id]);
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
        $this->assertTrue($this->hayAvisoCon($resultado['avisos'], 'no está dado de alta'));
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
