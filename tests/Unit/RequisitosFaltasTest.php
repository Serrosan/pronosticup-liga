<?php

namespace Tests\Unit;

use App\Services\MotorFaltas;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Las Faltas de "requisito de pronóstico" tienen que estar registradas en el
 * motor: Resultado Gafas no lo estaba y en la jornada 9 no se aplicó.
 */
class RequisitosFaltasTest extends TestCase
{
    public function test_resultado_gafas_esta_registrada_y_todos_los_tipos_son_validos(): void
    {
        $requisitos = (new ReflectionClassConstant(MotorFaltas::class, 'REQUISITOS_PRONOSTICO'))->getValue();

        $this->assertSame(['tipo' => 'Empate', 'cantidad' => 1], $requisitos['FAL-COM-EMPATE']);
        $this->assertSame(['tipo' => 'Empate', 'cantidad' => 2], $requisitos['FAL-PCOM-EMPATE']);

        foreach ($requisitos as $codigo => $requisito) {
            $this->assertContains($requisito['tipo'], ['Local', 'Empate', 'Visitante'], $codigo);
            $this->assertGreaterThan(0, $requisito['cantidad'], $codigo);
        }
    }
}
