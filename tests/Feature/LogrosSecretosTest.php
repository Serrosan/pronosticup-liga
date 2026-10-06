<?php

namespace Tests\Feature;

use App\Services\LogrosSecretos;
use Tests\TestCase;

class LogrosSecretosTest extends TestCase
{
    public function test_el_jugador_solo_recibe_titulo_icono_y_estado_nunca_como_se_consigue(): void
    {
        $lista = app(LogrosSecretos::class)->lista();

        $this->assertCount(count(LogrosSecretos::CATALOGO), $lista);

        foreach ($lista as $logro) {
            $this->assertSame(['clave', 'titulo', 'icono', 'estado'], array_keys($logro));
        }
    }

    public function test_un_logro_que_no_esta_activo_sale_en_construccion(): void
    {
        foreach (app(LogrosSecretos::class)->lista() as $logro) {
            $esperado = LogrosSecretos::CATALOGO[$logro['clave']]['activo'] ? 'bloqueado' : 'en_construccion';
            $this->assertSame($esperado, $logro['estado']);
        }
    }
}
