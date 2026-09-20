<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\EventoPartido;
use App\Services\EfectosCartas\BonusSiTarjetaRoja;
use PHPUnit\Framework\TestCase;

class BonusSiTarjetaRojaTest extends TestCase
{
    public function test_se_cumple_si_hay_tarjeta_roja(): void
    {
        $efecto = new BonusSiTarjetaRoja(2);
        $eventos = collect([new EventoPartido(['tipo_evento' => 'gol']), new EventoPartido(['tipo_evento' => 'tarjeta_roja'])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertTrue($resultado['cumplido']);
        $this->assertSame(2, $resultado['puntos']);
    }

    public function test_no_se_cumple_sin_tarjeta_roja(): void
    {
        $efecto = new BonusSiTarjetaRoja(2);
        $eventos = collect([new EventoPartido(['tipo_evento' => 'gol']), new EventoPartido(['tipo_evento' => 'tarjeta_amarilla'])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertFalse($resultado['cumplido']);
        $this->assertSame(0, $resultado['puntos']);
    }
}