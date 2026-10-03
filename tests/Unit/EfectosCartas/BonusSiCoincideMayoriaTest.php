<?php

namespace Tests\Unit\EfectosCartas;

use App\Services\EfectosCartas\BonusSiCoincideMayoria;
use PHPUnit\Framework\TestCase;

class BonusSiCoincideMayoriaTest extends TestCase
{
    public function test_suma_si_coincide_con_la_mayoria(): void
    {
        $efecto = new BonusSiCoincideMayoria(2);
        $resultado = $efecto->evaluar('Local', 'Local');
        $this->assertSame(2, $resultado['puntos']);
    }

    public function test_no_suma_si_no_coincide(): void
    {
        $efecto = new BonusSiCoincideMayoria(2);
        $resultado = $efecto->evaluar('Local', 'Visitante');
        $this->assertSame(0, $resultado['puntos']);
    }

    public function test_no_suma_si_no_hay_mayoria_calculable(): void
    {
        $efecto = new BonusSiCoincideMayoria(2);
        $resultado = $efecto->evaluar('Local', null);
        $this->assertSame(0, $resultado['puntos']);
    }
}