<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\ConfiguracionPuntos;
use App\Services\EfectosCartas\BonusFijoPartido;
use PHPUnit\Framework\TestCase;

class BonusFijoPartidoTest extends TestCase
{
    public function test_suma_el_bonus_fijo_a_los_puntos_base(): void
    {
        $carta = new BonusFijoPartido(3); // ej. Chute Extra Rara
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 1, tipoEventoReal: 'Acierto1x2', config: $config);

        $this->assertSame(4, $resultado['puntos']);
        $this->assertNull($resultado['nota']);
    }

    public function test_suma_el_bonus_incluso_si_el_pronostico_fue_un_fallo(): void
    {
        $carta = new BonusFijoPartido(1); // Chute Extra Común
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 0, tipoEventoReal: 'Fallo', config: $config);

        $this->assertSame(1, $resultado['puntos']);
    }
}