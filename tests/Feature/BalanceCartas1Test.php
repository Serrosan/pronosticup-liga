<?php

namespace Tests\Feature;

use App\Models\CategoriaCarta;
use App\Services\MotorEfectosCartas;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Primer balance de las cartas: números nuevos en el motor y textos del catálogo al día. */
class BalanceCartas1Test extends TestCase
{
    use RefreshDatabase;

    public function test_los_requisitos_de_casa_y_visita_suben(): void
    {
        $requisitos = (new \ReflectionClassConstant(MotorFaltas::class, 'REQUISITOS_PRONOSTICO'))->getValue();

        $this->assertSame(3, $requisitos['FAL-COM-CASA']['cantidad']);
        $this->assertSame(4, $requisitos['FAL-PCOM-CASA']['cantidad']);
        $this->assertSame(['tipo' => 'Local', 'cantidad' => 5], $requisitos['FAL-RAR-CASA']);
        $this->assertSame(6, $requisitos['FAL-LEG-CASA']['cantidad']);
        $this->assertSame([3, 4, 5, 6], [
            $requisitos['FAL-COM-VISITA']['cantidad'], $requisitos['FAL-PCOM-VISITA']['cantidad'],
            $requisitos['FAL-RAR-VISITA']['cantidad'], $requisitos['FAL-LEG-VISITA']['cantidad'],
        ]);
        $this->assertSame(1, $requisitos['FAL-COM-EMPATE']['cantidad'], 'Resultado Gafas no cambia');
    }

    public function test_ojo_de_halcon_y_crack_dan_mas_puntos(): void
    {
        $motor = app(MotorEfectosCartas::class);
        $numeros = function (string $metodo, string $codigo) use ($motor) {
            $registro = (new \ReflectionMethod($motor, $metodo))->invoke($motor);

            return array_values(array_filter((array) $registro[$codigo], 'is_int'));
        };

        $comun = $numeros('ajustadoresPartido', 'JUG-COM-OJOHALCON');
        $this->assertContains(3, $comun);
        $this->assertNotContains(2, $comun);

        $pocoComun = $numeros('ajustadoresPartido', 'JUG-PCOM-OJOHALCON');
        $this->assertContains(5, $pocoComun);
        $this->assertNotContains(4, $pocoComun);

        $crack = $numeros('bonosMultiPartido', 'JUG-LEG-CRACK');
        $this->assertContains(6, $crack);
        $this->assertNotContains(4, $crack);
    }

    public function test_la_migracion_pone_los_textos_al_dia_y_crea_la_casa_rara(): void
    {
        $faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        foreach ([['FAL-COM-CASA', 'Comun', '2🏠'], ['FAL-PCOM-CASA', 'PocoComun', '3🏠']] as [$codigo, $rareza, $insignia]) {
            DB::table('tipos_carta')->insert([
                'id_categoria' => $faltas->id, 'rareza' => $rareza, 'nombre' => 'Todo queda en casa', 'descripcion' => 'vieja',
                'imagen_url' => '/cartas/casa.png', 'insignia_corta' => $insignia, 'codigo_efecto' => $codigo, 'activa' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $migracion = require database_path('migrations/2026_11_02_120000_balance_cartas_1.php');
        $migracion->up();
        $migracion->up(); // dos veces: no duplica nada

        $comun = DB::table('tipos_carta')->where('codigo_efecto', 'FAL-COM-CASA')->first();
        $this->assertStringContainsString('al menos 3 victorias locales', $comun->descripcion);
        $this->assertSame('3🏠', $comun->insignia_corta);

        $rara = DB::table('tipos_carta')->where('codigo_efecto', 'FAL-RAR-CASA')->get();
        $this->assertCount(1, $rara);
        $this->assertSame(['Rara', '/cartas/casa.png', '5🏠', 1], [$rara[0]->rareza, $rara[0]->imagen_url, $rara[0]->insignia_corta, (int) $rara[0]->activa]);
    }
}
