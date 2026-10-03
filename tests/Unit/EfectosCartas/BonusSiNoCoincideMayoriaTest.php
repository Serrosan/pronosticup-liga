<?php

namespace Tests\Unit\EfectosCartas;

use App\Services\EfectosCartas\BonusSiNoCoincideMayoria;
use PHPUnit\Framework\TestCase;

class BonusSiNoCoincideMayoriaTest extends TestCase
{
    public function test_suma_si_NO_coincide_con_la_mayoria(): void
    {
        $efecto = new BonusSiNoCoincideMayoria(1);
        $resultado = $efecto->evaluar('Local', 'Visitante');
        $this->assertSame(1, $resultado['puntos']);
    }

    public function test_no_suma_si_coincide(): void
    {
        $efecto = new BonusSiNoCoincideMayoria(1);
        $resultado = $efecto->evaluar('Local', 'Local');
        $this->assertSame(0, $resultado['puntos']);
    }

    public function test_no_suma_si_no_hay_mayoria_calculable(): void
    {
        $efecto = new BonusSiNoCoincideMayoria(1);
        $resultado = $efecto->evaluar('Local', null);
        $this->assertSame(0, $resultado['puntos']);
    }
}