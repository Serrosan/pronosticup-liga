<?php

namespace Tests\Feature;

use App\Models\AliasJugadorLaliga;
use App\Models\CalendarioPartido;
use App\Models\CambioPlantilla;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Services\CambiosPlantillaService;
use App\Services\ImportadorPartidoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cola de /admin/cambios-plantilla: lo que apunta el importador de LaLiga
 * cuando no puede emparejar a alguien o ve otro dorsal, y lo que pasa al
 * resolverlo. Nada de aquí sale a internet (las altas van sin foto).
 */
class CambiosPlantillaTest extends TestCase
{
    use RefreshDatabase;

    private ImportadorPartidoDetalle $importador;
    private CambiosPlantillaService $cambios;
    private Temporada $temporada;
    private Equipo $local;
    private Equipo $visitante;
    private CalendarioPartido $partido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importador = app(ImportadorPartidoDetalle::class);
        $this->cambios = app(CambiosPlantillaService::class);

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

    private function crearJugador(Equipo $equipo, string $nombre, string $apellidos, ?int $dorsal = null, ?string $nombreCamiseta = null): Jugador
    {
        $jugador = Jugador::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos, 'nombre_camiseta' => $nombreCamiseta]);

        PlantillaTemporada::create([
            'id_jugador' => $jugador->id,
            'id_equipo' => $equipo->id,
            'id_temporada' => $this->temporada->id,
            'dorsal' => $dorsal,
            'fecha_incorporacion' => null,
            'fecha_salida' => null,
        ]);

        return $jugador;
    }

    private function persona(string $nombre, string $apellidos, ?string $apodo = null): array
    {
        return ['name' => "{$nombre} {$apellidos}", 'nickname' => $apodo ?? "{$nombre} {$apellidos}", 'firstname' => $nombre, 'lastname' => $apellidos];
    }

    /** Importa el partido con estos titulares locales (cada uno: [dorsal, persona]) y, opcionalmente, eventos. */
    private function importar(array $titulares, array $eventos = []): array
    {
        $starts = array_map(fn ($t) => [
            'shirt_number' => $t[0],
            'status' => 'start',
            'person' => $t[1],
            'photos' => ['003' => ['64x64' => 'https://assets.laliga.com/squad/2026/t1/p1/64x64/foto.png']],
        ], $titulares);

        return $this->importador->importar([
            'equipo_local' => 'Sevilla FC',
            'equipo_visitante' => 'Rayo Vallecano',
            'id_laliga_local' => 17,
            'id_laliga_visitante' => 14,
            'jornada' => 1,
            'formacion_local' => '4231',
            'formacion_visitante' => '4231',
            'lineups' => ['home' => ['starts' => $starts, 'subs' => []], 'away' => ['starts' => [], 'subs' => []]],
            'stats' => ['home' => [], 'away' => []],
            'events' => $eventos,
        ], $this->temporada->id);
    }

    private function pendiente(string $tipo = 'jugador'): ?CambioPlantilla
    {
        return CambioPlantilla::where('tipo', $tipo)->where('estado', 'pendiente')->first();
    }

    private function datosDeAlta(array $extra = []): array
    {
        return array_merge(['nombre' => 'Aimar', 'apellidos' => 'Blázquez', 'posicion' => 'Delantero', 'dorsal' => 37], $extra);
    }

    public function test_un_jugador_sin_emparejar_queda_apuntado_una_sola_vez_con_lo_que_manda_laliga(): void
    {
        $this->importar([[37, $this->persona('Aimar', 'Blázquez', 'Aimar')]]);
        $this->importar([[37, $this->persona('Aimar', 'Blázquez', 'Aimar')]]);

        $this->assertSame(1, CambioPlantilla::count());

        $cambio = $this->pendiente();
        $this->assertSame($this->local->id, $cambio->id_equipo);
        $this->assertSame('no_existe', $cambio->pista);
        $this->assertSame('Aimar Blázquez', $cambio->nombre_laliga);
        $this->assertSame('Aimar', $cambio->apodo_laliga);
        $this->assertSame(37, $cambio->dorsal);
        $this->assertSame([$this->partido->id], $cambio->partidos);
        $this->assertStringContainsString('assets.laliga.com', $cambio->foto_laliga);
    }

    public function test_la_pista_distingue_dudoso_y_existe_en_otra_plantilla(): void
    {
        $isaac = $this->crearJugador($this->local, 'Isaac', 'Palazón');
        $maffeo = $this->crearJugador($this->visitante, 'Pablo', 'Maffeo');

        $this->importar([
            [7, $this->persona('Isi', 'Palazón', 'Isi')],
            [2, $this->persona('Pablo', 'Maffeo', 'Maffeo')],
        ]);

        $dudoso = CambioPlantilla::where('clave', 'isi palazon')->first();
        $this->assertSame('podria_ser', $dudoso->pista);
        $this->assertSame($isaac->id, $dudoso->id_jugador_sugerido);

        $fuera = CambioPlantilla::where('clave', 'pablo maffeo')->first();
        $this->assertSame('fuera_de_plantilla', $fuera->pista);
        $this->assertSame($maffeo->id, $fuera->id_jugador_sugerido);
    }

    public function test_dar_de_alta_crea_jugador_ficha_y_equivalencia_y_la_siguiente_importacion_ya_empareja(): void
    {
        $persona = $this->persona('Aimar', 'Blázquez', 'Aimar');
        $this->importar([[37, $persona]]);

        $resultado = $this->cambios->darDeAlta($this->pendiente(), $this->datosDeAlta(['nombre_camiseta' => 'Aimar', 'pie' => 'Izquierdo']));
        $jugador = $resultado['jugador'];

        $this->assertNull($resultado['aviso']);
        $this->assertDatabaseHas('jugadores', ['id' => $jugador->id, 'nombre' => 'Aimar', 'posicion' => 'Delantero', 'pie' => 'Izquierdo']);
        $this->assertDatabaseHas('plantilla_temporada', ['id_jugador' => $jugador->id, 'id_equipo' => $this->local->id, 'dorsal' => 37, 'fecha_salida' => null]);
        $this->assertDatabaseHas('alias_jugador_laliga', ['id_equipo' => $this->local->id, 'clave' => 'aimar blazquez', 'id_jugador' => $jugador->id]);

        $cambio = CambioPlantilla::first();
        $this->assertSame('resuelto', $cambio->estado);
        $this->assertSame('alta', $cambio->resuelto_como);
        $this->assertSame([$this->partido->id], $cambio->partidos_por_reimportar);

        // Aunque luego se le cambie el nombre por completo, LaLiga lo sigue encontrando.
        $jugador->update(['nombre' => 'El Mago', 'apellidos' => 'De Paterna', 'nombre_camiseta' => null]);

        $resultado = $this->importar([[37, $persona]], [
            ['match_event_kind' => ['id' => 1, 'name' => 'Goal', 'collection' => 'goal'], 'lineup' => ['team' => ['id' => 17], 'person' => $persona], 'clock' => '88'],
        ]);

        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $jugador->id, 'dorsal' => 37]);
        $this->assertDatabaseHas('eventos_partido', ['id_partido' => $this->partido->id, 'id_jugador' => $jugador->id, 'tipo_evento' => 'gol']);
        $this->assertSame('resuelto', CambioPlantilla::first()->estado);
    }

    public function test_no_se_puede_dar_de_alta_con_un_dorsal_que_ya_lleva_otro(): void
    {
        $this->crearJugador($this->local, 'Jesús', 'Navas', dorsal: 37);
        $this->importar([[37, $this->persona('Aimar', 'Blázquez', 'Aimar')]]);
        $cambio = CambioPlantilla::where('clave', 'aimar blazquez')->first();

        try {
            $this->cambios->darDeAlta($cambio, $this->datosDeAlta());
            $this->fail('Debería haber avisado de que el dorsal está ocupado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Jesús Navas', $e->getMessage());
        }

        $this->assertSame(1, Jugador::count());
        $this->assertSame('pendiente', $cambio->refresh()->estado);
    }

    public function test_es_este_jugador_deja_la_equivalencia_y_puede_ponerle_el_dorsal(): void
    {
        $isaac = $this->crearJugador($this->local, 'Isaac', 'Palazón');
        $persona = $this->persona('Isi', 'Palazón', 'Isi');
        $this->importar([[7, $persona]]);

        $this->cambios->asignar($this->pendiente(), $isaac, actualizarDorsal: true);

        $this->assertDatabaseHas('alias_jugador_laliga', ['clave' => 'isi palazon', 'id_jugador' => $isaac->id]);
        $this->assertDatabaseHas('plantilla_temporada', ['id_jugador' => $isaac->id, 'dorsal' => 7]);

        $resultado = $this->importar([[7, $persona]]);

        $this->assertEmpty($resultado['avisos']);
        $this->assertDatabaseHas('alineaciones_jugador', ['id_partido' => $this->partido->id, 'id_jugador' => $isaac->id]);
        $this->assertSame(0, CambioPlantilla::where('estado', 'pendiente')->count());
    }

    public function test_no_se_puede_asignar_a_un_jugador_que_no_esta_en_la_plantilla_de_ese_equipo(): void
    {
        $delRayo = $this->crearJugador($this->visitante, 'Isaac', 'Palazón');
        $this->importar([[7, $this->persona('Isi', 'Palazón', 'Isi')]]);
        $cambio = CambioPlantilla::where('clave', 'isi palazon')->first();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('no está en la plantilla de Sevilla');

        $this->cambios->asignar($cambio, $delRayo, actualizarDorsal: false);
    }

    public function test_un_ignorado_deja_de_avisar_y_no_se_reabre(): void
    {
        $persona = $this->persona('Aimar', 'Blázquez', 'Aimar');
        $this->importar([[37, $persona]]);
        $this->cambios->ignorar($this->pendiente());

        $resultado = $this->importar([[37, $persona]]);

        $this->assertEmpty($resultado['avisos']);
        $this->assertSame('ignorado', CambioPlantilla::first()->estado);
        $this->assertDatabaseCount('alineaciones_jugador', 0);
    }

    public function test_un_dorsal_distinto_se_propone_y_al_aplicarlo_cambia_la_plantilla(): void
    {
        $morente = $this->crearJugador($this->local, 'Tete', 'Morente', dorsal: 15);
        $persona = $this->persona('Tete', 'Morente', 'Tete Morente');

        $resultado = $this->importar([[20, $persona]]);

        $this->assertEmpty($resultado['avisos']);
        $cambio = $this->pendiente('dorsal');
        $this->assertSame($morente->id, $cambio->id_jugador_sugerido);
        $this->assertSame(20, $cambio->dorsal);
        $this->assertStringContainsString('lleva el 15', $cambio->motivo);
        // Solo se propone: la plantilla sigue como estaba.
        $this->assertDatabaseHas('plantilla_temporada', ['id_jugador' => $morente->id, 'dorsal' => 15]);

        $this->cambios->aplicarDorsal($cambio);

        $this->assertDatabaseHas('plantilla_temporada', ['id_jugador' => $morente->id, 'dorsal' => 20]);
        $this->assertSame('resuelto', $cambio->refresh()->estado);

        $this->importar([[20, $persona]]);
        $this->assertSame(0, CambioPlantilla::where('estado', 'pendiente')->count());
    }

    public function test_aplicar_un_dorsal_que_lleva_otro_jugador_avisa_y_no_cambia_nada(): void
    {
        $morente = $this->crearJugador($this->local, 'Tete', 'Morente', dorsal: 15);
        $this->crearJugador($this->local, 'Jesús', 'Navas', dorsal: 20);
        $this->importar([[20, $this->persona('Tete', 'Morente', 'Tete Morente')]]);
        $cambio = CambioPlantilla::where('tipo', 'dorsal')->where('id_jugador_sugerido', $morente->id)->first();

        try {
            $this->cambios->aplicarDorsal($cambio);
            $this->fail('Debería haber avisado de que el dorsal está ocupado.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Jesús Navas', $e->getMessage());
        }

        $this->assertDatabaseHas('plantilla_temporada', ['id_jugador' => $morente->id, 'dorsal' => 15]);
        $this->assertSame('pendiente', $cambio->refresh()->estado);
    }

    public function test_si_se_arregla_por_otro_lado_el_pendiente_se_cierra_solo(): void
    {
        $persona = $this->persona('Aimar', 'Blázquez', 'Aimar');
        $this->importar([[37, $persona]]);
        $this->assertNotNull($this->pendiente());

        // Lo da de alta a mano, desde la pantalla de jugadores de siempre.
        $jugador = $this->crearJugador($this->local, 'Aimar', 'Blázquez', dorsal: 37);

        $this->importar([[37, $persona]]);

        $cambio = CambioPlantilla::where('tipo', 'jugador')->first();
        $this->assertSame('resuelto', $cambio->estado);
        $this->assertSame('automatico', $cambio->resuelto_como);
        $this->assertSame($jugador->id, $cambio->id_jugador_resuelto);
    }

    public function test_los_candidatos_son_la_plantilla_del_equipo_y_la_busqueda_llega_a_otros_equipos(): void
    {
        $delSevilla = $this->crearJugador($this->local, 'Isaac', 'Romero', dorsal: 9);
        $delRayo = $this->crearJugador($this->visitante, 'Isaac', 'Palazón', dorsal: 7);
        $this->importar([[30, $this->persona('Aimar', 'Blázquez', 'Aimar')]]);
        $cambio = $this->pendiente();

        $sinBuscar = collect($this->cambios->candidatos($cambio, null))->pluck('id')->all();
        $buscando = collect($this->cambios->candidatos($cambio, 'Isaac'))->pluck('id')->all();

        $this->assertSame([$delSevilla->id], $sinBuscar);
        $this->assertEqualsCanonicalizing([$delSevilla->id, $delRayo->id], $buscando);
    }

    public function test_el_alias_solo_vale_para_su_equipo(): void
    {
        $jugador = $this->crearJugador($this->visitante, 'Otro', 'Distinto');
        AliasJugadorLaliga::create(['id_equipo' => $this->visitante->id, 'clave' => 'aimar blazquez', 'id_jugador' => $jugador->id]);

        $resultado = $this->importar([[37, $this->persona('Aimar', 'Blázquez', 'Aimar')]]);

        $this->assertNotEmpty($resultado['avisos']);
        $this->assertDatabaseCount('alineaciones_jugador', 0);
    }
}
