<?php

namespace Tests\Unit;

use App\Services\MotorFaltas;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Las Faltas de "requisito de pronóstico" tienen que estar registradas en el
 * motor: Resultado Gafas no lo estaba y en la jornada 9 no se aplicó.
 * Las cantidades son las que dice la descripción de cada carta en el catálogo.
 */
class RequisitosFaltasTest extends TestCase
{
    public function test_las_cuatro_resultado_gafas_estan_registradas_y_todos_los_tipos_son_validos(): void
    {
        $requisitos = (new ReflectionClassConstant(MotorFaltas::class, 'REQUISITOS_PRONOSTICO'))->getValue();

        foreach (['FAL-COM-EMPATE' => 1, 'FAL-PCOM-EMPATE' => 2, 'FAL-RAR-EMPATE' => 3, 'FAL-LEG-EMPATE' => 4] as $codigo => $cantidad) {
            $this->assertSame(['tipo' => 'Empate', 'cantidad' => $cantidad], $requisitos[$codigo], $codigo);
        }

        foreach ($requisitos as $codigo => $requisito) {
            $this->assertContains($requisito['tipo'], ['Local', 'Empate', 'Visitante'], $codigo);
            $this->assertGreaterThan(0, $requisito['cantidad'], $codigo);
        }
    }
}
