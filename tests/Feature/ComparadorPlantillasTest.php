<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ComparadorPlantillasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Comparar plantillas con football-data: consultar equipo a equipo, las listas
 * que salen y las acciones. La API se simula: los tests nunca salen a internet.
 */
class ComparadorPlantillasTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Equipo $betis;
    private Equipo $sevilla;
    private ComparadorPlantillasService $comparador;

    protected function setUp(): void
    {
        parent::setUp();

        ComparadorPlantillasService::$espera = 0;
        $this->temporada = Temporada::factory()->create();
        $this->betis = Equipo::factory()->create(['nombre' => 'Real Betis', 'nombre_corto' => 'Betis', 'id_equipo_api' => 90]);
        $this->sevilla = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla', 'id_equipo_api' => 559]);
        $this->comparador = app(ComparadorPlantillasService::class);

        Http::fake([
            'api.football-data.org/v4/teams/90' => Http::response(['squad' => [
                ['id' => 100, 'name' => 'Isco', 'position' => 'Midfield'],
                ['id' => 200, 'name' => 'Dodi Lukebakio', 'position' => 'Right Winger'],
                ['id' => 401, 'name' => 'Pepe Nuevo', 'position' => 'Centre-Back'],
                ['id' => 400, 'name' => 'Fichaje Desconocido', 'position' => 'Goalkeeper', 'dateOfBirth' => '2001-05-04', 'nationality' => 'Spain'],
            ]]),
            'api.football-data.org/v4/teams/559' => Http::response(['squad' => [
                ['id' => 500, 'name' => 'Kike Salas', 'position' => 'Defence'],
            ]]),
        ]);
    }

    private function jugador(string $nombre, ?string $apellidos, ?int $idApi, Equipo $equipo, ?string $camiseta = null): Jugador
    {
        $jugador = Jugador::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos, 'nombre_camiseta' => $camiseta, 'id_externo_api' => $idApi, 'dado_de_baja_en' => null]);
        PlantillaTemporada::create([
            'id_jugador' => $jugador->id, 'id_equipo' => $equipo->id, 'id_temporada' => $this->temporada->id,
            'dorsal' => null, 'fecha_incorporacion' => null, 'fecha_salida' => null,
        ]);

        return $jugador;
    }

    private function consultarTodo(): array
    {
        $this->comparador->consultarEquipo($this->betis);
        $this->comparador->consultarEquipo($this->sevilla);

        return $this->comparador->resultado();
    }

    public function test_salen_traspasos_salidas_altas_dudosos_y_sin_identificador(): void
    {
        $this->jugador('Francisco', 'Román Alarcón', 100, $this->betis, 'Isco');      // Isco: sin cambios (cuadra por la camiseta)
        $lukebakio = $this->jugador('Dodi', 'Lukebakio', 200, $this->sevilla);       // ahora en el Betis
        $navas = $this->jugador('Jesús', 'Navas', 300, $this->sevilla);              // ya no está
        $pepe = $this->jugador('Pepe', 'Nuevo', null, $this->betis);                 // sin enlazar
        $perez = $this->jugador('Juan', 'Pérez', 500, $this->sevilla);               // el 500 es Kike Salas

        $r = $this->consultarTodo();

        $this->assertTrue($r['todos']);
        $this->assertSame([$lukebakio->id], array_column($r['traspasos'], 'id_jugador'));
        $this->assertSame($this->betis->id, $r['traspasos'][0]['id_equipo_destino']);
        $this->assertSame([$navas->id], array_column($r['bajas'], 'id_jugador'));
        $this->assertSame([$perez->id], array_column($r['dudosos'], 'id_jugador'));
        $this->assertEqualsCanonicalizing([400, 401], array_column($r['altas'], 'id_api'));
        $this->assertSame([$pepe->id], array_column($r['sin_id'], 'id_jugador'));
        $this->assertSame(401, $r['sin_id'][0]['candidatos'][0]['id_api'], 'El candidato más parecido va primero');

        $alta401 = collect($r['altas'])->firstWhere('id_api', 401);
        $this->assertSame($pepe->id, $alta401['candidatos'][0]['id_jugador']);
        $this->assertSame('Defensa', $alta401['posicion']);
    }

    public function test_con_equipos_sin_consultar_no_se_dan_traspasos_ni_salidas(): void
    {
        $this->jugador('Dodi', 'Lukebakio', 200, $this->sevilla);
        $this->comparador->consultarEquipo($this->sevilla);

        $r = $this->comparador->resultado();

        $this->assertFalse($r['todos']);
        $this->assertSame([], $r['traspasos']);
        $this->assertSame([], $r['bajas']);
    }

    public function test_traspaso_cierra_la_ficha_el_dia_antes_y_abre_la_nueva(): void
    {
        $lukebakio = $this->jugador('Dodi', 'Lukebakio', 200, $this->sevilla);
        $this->consultarTodo();

        $this->comparador->traspasar($lukebakio, $this->betis->id, 11, '2027-01-15');

        $vieja = PlantillaTemporada::where('id_jugador', $lukebakio->id)->where('id_equipo', $this->sevilla->id)->first();
        $nueva = PlantillaTemporada::where('id_jugador', $lukebakio->id)->where('id_equipo', $this->betis->id)->first();
        $this->assertSame('2027-01-14', substr((string) $vieja->fecha_salida, 0, 10));
        $this->assertSame('2027-01-15', substr((string) $nueva->fecha_incorporacion, 0, 10));
        $this->assertSame(11, (int) $nueva->dorsal);
        $this->assertSame([], $this->comparador->resultado()['traspasos']);
    }

    public function test_alta_crea_el_jugador_con_su_identificador_y_su_ficha(): void
    {
        $this->consultarTodo();

        $this->comparador->darDeAlta(400, $this->betis->id, 'Portero', 13, '2027-01-02');

        $nuevo = Jugador::where('id_externo_api', 400)->firstOrFail();
        $this->assertSame('Fichaje', $nuevo->nombre);
        $this->assertSame('Desconocido', $nuevo->apellidos);
        $this->assertSame('Portero', $nuevo->posicion);
        $this->assertTrue(PlantillaTemporada::where('id_jugador', $nuevo->id)->where('id_equipo', $this->betis->id)->where('dorsal', 13)->exists());

        $this->expectException(RuntimeException::class);
        $this->comparador->darDeAlta(400, $this->betis->id, 'Portero', null, '2027-01-02');
    }

    public function test_salida_cierra_la_ficha_y_si_se_pide_da_de_baja(): void
    {
        $navas = $this->jugador('Jesús', 'Navas', 300, $this->sevilla);
        $this->consultarTodo();

        $this->comparador->salida($navas, '2027-01-31', true);

        $this->assertNotNull(PlantillaTemporada::where('id_jugador', $navas->id)->value('fecha_salida'));
        $this->assertNotNull($navas->fresh()->dado_de_baja_en);
        $this->assertSame([], $this->comparador->resultado()['bajas']);
    }

    public function test_no_deja_poner_un_identificador_que_ya_tiene_otro(): void
    {
        $this->jugador('Francisco', 'Román Alarcón', 100, $this->betis, 'Isco');
        $pepe = $this->jugador('Pepe', 'Nuevo', null, $this->betis);

        $this->expectException(RuntimeException::class);
        $this->comparador->vincular($pepe, 100);
    }

    public function test_un_dudoso_confirmado_deja_de_salir(): void
    {
        $perez = $this->jugador('Juan', 'Pérez', 500, $this->sevilla);
        $this->assertCount(1, $this->consultarTodo()['dudosos']);

        $this->comparador->confirmarIdentificador($perez);

        $this->assertSame([], $this->comparador->resultado()['dudosos']);
    }

    public function test_solo_el_superadmin_entra(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/comparador-plantillas')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['es_superadmin' => true])->save();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/comparador-plantillas')->assertOk()->assertJsonPath('data.total', 2);
        $this->postJson("/api/v1/admin/comparador-plantillas/equipos/{$this->betis->id}/consultar")->assertOk()->assertJsonPath('data.jugadores', 4);
    }
}
