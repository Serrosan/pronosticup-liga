<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\CartaUsuario;
use App\Services\EfectosCartas\ProtegeAutomatico;
use PHPUnit\Framework\TestCase;

class ProtegeAutomaticoTest extends TestCase
{
    public function test_protege_hasta_la_cantidad_configurada(): void
    {
        $protector = new ProtegeAutomatico(2); // Amuleto Legendaria
        $carta = new CartaUsuario();

        $resultado = $protector->partidosAProteger($carta, collect([10, 20, 30]));

        $this->assertCount(2, $resultado);
    }

    public function test_no_protege_nada_si_no_hay_fallos(): void
    {
        $protector = new ProtegeAutomatico(1); // Amuleto Rara
        $carta = new CartaUsuario();

        $resultado = $protector->partidosAProteger($carta, collect());

        $this->assertTrue($resultado->isEmpty());
    }
}