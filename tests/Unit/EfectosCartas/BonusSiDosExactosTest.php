<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\EventoPuntos;
use App\Services\EfectosCartas\BonusSiDosExactos;
use PHPUnit\Framework\TestCase;

class BonusSiDosExactosTest extends TestCase
{
    public function test_se_cumple_con_2_o_mas_exactos(): void
    {
        $efecto = new BonusSiDosExactos(4);
        $eventos = collect([
            new EventoPuntos(['tipo_evento' => 'AciertoExacto']),
            new EventoPuntos(['tipo_evento' => 'AciertoExacto']),
            new EventoPuntos(['tipo_evento' => 'Fallo']),
        ]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertTrue($resultado['cumplido']);
        $this->assertSame(4, $resultado['puntos']);
    }

    public function test_no_se_cumple_con_solo_1_exacto(): void
    {
        $efecto = new BonusSiDosExactos(4);
        $eventos = collect([new EventoPuntos(['tipo_evento' => 'AciertoExacto']), new EventoPuntos(['tipo_evento' => 'Fallo'])]);

        $resultado = $efecto->evaluar($eventos);

        $this->assertFalse($resultado['cumplido']);
        $this->assertSame(0, $resultado['puntos']);
    }
}