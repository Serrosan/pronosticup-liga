<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\ConfiguracionPuntos;
use App\Services\EfectosCartas\BonusSiExacto;
use PHPUnit\Framework\TestCase;

class BonusSiExactoTest extends TestCase
{
    public function test_suma_el_bonus_si_el_resultado_fue_exacto(): void
    {
        $carta = new BonusSiExacto(2); // Ojo de Halcón Común
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 5, tipoEventoReal: 'AciertoExacto', config: $config);

        $this->assertSame(7, $resultado['puntos']);
    }

    public function test_no_suma_nada_si_no_fue_exacto(): void
    {
        $carta = new BonusSiExacto(2);
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 1, tipoEventoReal: 'Acierto1x2', config: $config);

        $this->assertSame(1, $resultado['puntos']);
    }

    public function test_no_suma_nada_en_un_fallo(): void
    {
        $carta = new BonusSiExacto(4); // Ojo de Halcón Poco común
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 0, tipoEventoReal: 'Fallo', config: $config);

        $this->assertSame(0, $resultado['puntos']);
    }
}