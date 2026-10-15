<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Services\ComparadorPlantillasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Completar identificadores": solo asigna cuando está claro y dice qué ha hecho.
 */
class CompletarIdsApiTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Equipo $betis;

    protected function setUp(): void
    {
        parent::setUp();

        ComparadorPlantillasService::$espera = 0;
        $this->temporada = Temporada::factory()->create();
        $this->betis = Equipo::factory()->create(['nombre' => 'Real Betis', 'id_equipo_api' => 90]);

        Http::fake(['api.football-data.org/v4/teams/90' => Http::response(['squad' => [
            ['id' => 1, 'name' => 'Isco'],
            ['id' => 2, 'name' => 'Marc Bartra'],
            ['id' => 3, 'name' => 'Álex Moreno'],
            ['id' => 4, 'name' => 'Álex Romero'],
            ['id' => 5, 'name' => 'Giovani Lo Celso'],
        ]])]);
    }

    private function jugador(string $nombre, string $apellidos, ?string $camiseta = null): Jugador
    {
        $jugador = Jugador::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos, 'nombre_camiseta' => $camiseta, 'id_externo_api' => null, 'dado_de_baja_en' => null]);
        PlantillaTemporada::create(['id_jugador' => $jugador->id, 'id_equipo' => $this->betis->id, 'id_temporada' => $this->temporada->id, 'fecha_salida' => null]);

        return $jugador;
    }

    public function test_asigna_lo_claro_y_deja_lo_dudoso(): void
    {
        $isco = $this->jugador('Francisco', 'Román Alarcón', 'Isco');      // por la camiseta
        $bartra = $this->jugador('Marc', 'Bartra Aregall');                  // apellido en común
        $alex = $this->jugador('Álex', 'Moreno-Romero');                     // Moreno y Romero casi empatan
        $nadie = $this->jugador('Pablo', 'García Fernández');                // no se parece a nadie

        $this->artisan('liga:completar-ids-api')
            ->expectsOutputToContain('Asignados: 2')
            ->expectsOutputToContain('SIN RESOLVER (2)')
            ->assertSuccessful();

        $this->assertSame(1, (int) $isco->fresh()->id_externo_api);
        $this->assertSame(2, (int) $bartra->fresh()->id_externo_api);
        $this->assertNull($alex->fresh()->id_externo_api, 'Con dos casi iguales no elige');
        $this->assertNull($nadie->fresh()->id_externo_api);
    }

    public function test_en_seco_no_guarda_nada(): void
    {
        $isco = $this->jugador('Francisco', 'Román Alarcón', 'Isco');

        $this->artisan('liga:completar-ids-api --en-seco')
            ->expectsOutputToContain('Se asignarían: 1')
            ->assertSuccessful();

        $this->assertNull($isco->fresh()->id_externo_api);
    }
}
