<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\ConfiguracionPuntos;
use App\Services\EfectosCartas\Doblete;
use PHPUnit\Framework\TestCase;

class DobleteTest extends TestCase
{
    public function test_duplica_los_puntos_base(): void
    {
        $carta = new Doblete();
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 2, tipoEventoReal: 'AciertoDiferencia', config: $config);

        $this->assertSame(4, $resultado['puntos']);
    }

    public function test_duplicar_cero_sigue_siendo_cero(): void
    {
        $carta = new Doblete();
        $config = new ConfiguracionPuntos();

        $resultado = $carta->calcular(puntosBase: 0, tipoEventoReal: 'Fallo', config: $config);

        $this->assertSame(0, $resultado['puntos']);
    }
}