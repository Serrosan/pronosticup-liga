<?php

namespace Tests\Unit\EfectosCartas;

use App\Models\CartaUsuario;
use App\Services\EfectosCartas\ProtegeElegido;
use PHPUnit\Framework\TestCase;

class ProtegeElegidoTest extends TestCase
{
    public function test_protege_el_partido_elegido_si_esta_entre_los_fallados(): void
    {
        $carta = new CartaUsuario(['id_partido' => 42]);
        $protector = new ProtegeElegido();

        $resultado = $protector->partidosAProteger($carta, collect([10, 42, 99]));

        $this->assertSame([42], $resultado->values()->all());
    }

    public function test_no_protege_nada_si_el_elegido_no_esta_entre_los_fallados(): void
    {
        $carta = new CartaUsuario(['id_partido' => 42]);
        $protector = new ProtegeElegido();

        $resultado = $protector->partidosAProteger($carta, collect([10, 99]));

        $this->assertTrue($resultado->isEmpty());
    }
}