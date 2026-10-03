<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\ConfiguracionPuntos;
use App\Services\EfectosCartas\PlenoGarantizado;
use PHPUnit\Framework\TestCase;

class PlenoGarantizadoTest extends TestCase
{
    public function test_si_ya_era_exacto_no_cambia_nada_ni_pone_nota(): void
    {
        $carta = new PlenoGarantizado();
        $config = new ConfiguracionPuntos(['puntos_exacto' => 5]);

        $resultado = $carta->calcular(puntosBase: 5, tipoEventoReal: 'AciertoExacto', config: $config);

        $this->assertSame(5, $resultado['puntos']);
        $this->assertNull($resultado['nota']);
    }

    public function test_si_fue_fallo_suma_los_puntos_de_exacto_y_deja_nota(): void
    {
        $carta = new PlenoGarantizado();
        $config = new ConfiguracionPuntos(['puntos_exacto' => 5]);

        $resultado = $carta->calcular(puntosBase: 0, tipoEventoReal: 'Fallo', config: $config);

        $this->assertSame(5, $resultado['puntos']);
        $this->assertStringContainsString('Pleno Garantizado', $resultado['nota']);
        $this->assertStringContainsString('Fallo', $resultado['nota']);
    }

    public function test_si_acerto_signo_tambien_se_le_suman_los_puntos_de_exacto(): void
    {
        $carta = new PlenoGarantizado();
        $config = new ConfiguracionPuntos(['puntos_exacto' => 5]);

        $resultado = $carta->calcular(puntosBase: 1, tipoEventoReal: 'Acierto1x2', config: $config);

        $this->assertSame(6, $resultado['puntos']);
        $this->assertNotNull($resultado['nota']);
    }
}