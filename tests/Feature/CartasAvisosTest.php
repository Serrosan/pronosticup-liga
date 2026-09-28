<?php

namespace Tests\Unit;

use App\Notifications\CartasResueltas;
use App\Notifications\CartasSinJugar;
use PHPUnit\Framework\TestCase;

class CartasAvisosTest extends TestCase
{
    private function datos($notificacion): array
    {
        return $notificacion->toDatabase(new \stdClass);
    }

    public function test_cartas_sin_jugar_guarda_jornada_y_liga_para_no_repetirse(): void
    {
        $datos = $this->datos(new CartasSinJugar(jornada: 7, idLiga: 3, cantidad: 2, nombreLiga: 'BasiLiga'));

        $this->assertSame(7, $datos['jornada']);
        $this->assertSame(3, $datos['id_liga']);
        $this->assertStringContainsString('2 cartas de Jugadas', $datos['mensaje']);
        $this->assertStringContainsString('BasiLiga', $datos['mensaje']);
    }

    public function test_cartas_sin_jugar_habla_en_singular_con_una_carta(): void
    {
        $datos = $this->datos(new CartasSinJugar(jornada: 7, idLiga: 3, cantidad: 1, nombreLiga: 'BasiLiga'));

        $this->assertStringContainsString('1 carta de Jugadas', $datos['mensaje']);
        $this->assertStringNotContainsString('1 cartas', $datos['mensaje']);
    }

    public function test_cartas_resueltas_concuerda_en_singular_y_plural(): void
    {
        $una = $this->datos(new CartasResueltas(jornada: 7, total: 1, cumplidas: 1, puntos: 2))['mensaje'];
        $varias = $this->datos(new CartasResueltas(jornada: 7, total: 3, cumplidas: 2, puntos: 5))['mensaje'];

        $this->assertStringContainsString('se ha resuelto tu carta y sumó 2 pt', $una);
        $this->assertStringContainsString('se han resuelto tus 3 cartas y sumaron 5 pt', $varias);
    }

    public function test_cartas_resueltas_sin_puntos_lo_dice_claro(): void
    {
        $mensaje = $this->datos(new CartasResueltas(jornada: 7, total: 2, cumplidas: 0, puntos: 0))['mensaje'];

        $this->assertStringContainsString('no sumaron puntos', $mensaje);
    }

    public function test_los_avisos_van_solo_por_la_campana(): void
    {
        $this->assertSame(['database'], (new CartasSinJugar(1, 1, 1, 'L'))->via(new \stdClass));
        $this->assertSame(['database'], (new CartasResueltas(1, 1, 1, 1))->via(new \stdClass));
    }
}