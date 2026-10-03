<?php

namespace Tests\Unit;

use App\Notifications\CartasRepartidas;
use PHPUnit\Framework\TestCase;

class CartasRepartidasTest extends TestCase
{
    private function mensaje(CartasRepartidas $notificacion): string
    {
        return $notificacion->toDatabase(new \stdClass)['mensaje'];
    }

    public function test_reparto_semanal_indica_jornada_y_cantidad(): void
    {
        $mensaje = $this->mensaje(new CartasRepartidas(cantidad: 3, jornada: 7));

        $this->assertStringContainsString('jornada 7', $mensaje);
        $this->assertStringContainsString('3 cartas nuevas', $mensaje);
        $this->assertStringNotContainsString('bonus', $mensaje);
        $this->assertStringNotContainsString('tope', $mensaje);
    }

    public function test_con_una_sola_carta_habla_en_singular(): void
    {
        $mensaje = $this->mensaje(new CartasRepartidas(cantidad: 1, jornada: 7));

        $this->assertStringContainsString('una carta nueva', $mensaje);
        $this->assertStringContainsString('Ábrela', $mensaje);
    }

    public function test_menciona_el_bonus_top3_solo_si_lo_hay(): void
    {
        $mensaje = $this->mensaje(new CartasRepartidas(cantidad: 4, jornada: 7, posicionTop3: 2));

        $this->assertStringContainsString('bonus', $mensaje);
        $this->assertStringContainsString('2º', $mensaje);
    }

    public function test_avisa_si_supera_el_tope_de_la_mano(): void
    {
        $mensaje = $this->mensaje(new CartasRepartidas(cantidad: 3, jornada: 7, sobreElTope: true));

        $this->assertStringContainsString('tope', $mensaje);
    }

    public function test_el_reparto_inicial_no_habla_de_jornada(): void
    {
        $mensaje = $this->mensaje(new CartasRepartidas(cantidad: 5, inicial: true));

        $this->assertStringContainsString('Arranca el modo Cartas', $mensaje);
        $this->assertStringNotContainsString('jornada', $mensaje);
    }

    public function test_va_solo_por_la_campana_y_marcada_como_importante(): void
    {
        $notificacion = new CartasRepartidas(cantidad: 2, jornada: 7);

        $this->assertSame(['database'], $notificacion->via(new \stdClass));
        $this->assertTrue($notificacion->toDatabase(new \stdClass)['importante']);
        $this->assertSame('cartas_repartidas', $notificacion->toDatabase(new \stdClass)['tipo']);
    }
}