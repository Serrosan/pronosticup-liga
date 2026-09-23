<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\EventoPartido;
use App\Services\EfectosCartas\BonusSiGolEnFranja;
use PHPUnit\Framework\TestCase;

class BonusSiGolEnFranjaTest extends TestCase
{
    public function test_se_cumple_con_gol_dentro_de_la_franja(): void
    {
        $efecto = new BonusSiGolEnFranja(30, 60, 2);
        $eventos = collect([new EventoPartido(['tipo_evento' => 'gol', 'minuto' => 45])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertTrue($resultado['cumplido']);
        $this->assertSame(2, $resultado['puntos']);
    }

    public function test_no_se_cumple_con_gol_fuera_de_la_franja(): void
    {
        $efecto = new BonusSiGolEnFranja(60, 90, 3);
        $eventos = collect([new EventoPartido(['tipo_evento' => 'gol', 'minuto' => 20])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertFalse($resultado['cumplido']);
        $this->assertSame(0, $resultado['puntos']);
    }

    public function test_cuenta_gol_en_propia_tambien(): void
    {
        $efecto = new BonusSiGolEnFranja(0, 30, 1);
        $eventos = collect([new EventoPartido(['tipo_evento' => 'gol_en_propia', 'minuto' => 10])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertTrue($resultado['cumplido']);
    }
}