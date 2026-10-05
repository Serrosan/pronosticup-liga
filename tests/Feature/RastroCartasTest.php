<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\Temporada;
use App\Services\RastroCartasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rastro de cartas solo lee. Aquí se comprueba lo que no depende del
 * catálogo de cartas: cómo clasifica cada carta por su código de efecto (de
 * eso sale la explicación) y que una liga sin cartas no rompe nada.
 */
class RastroCartasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_carta_del_catalogo_se_clasifica_por_lo_que_necesita_para_resolverse(): void
    {
        $servicio = app(RastroCartasService::class);

        $esperado = [
            'JUG-COM-CHUTE' => 'partido',
            'JUG-PCOM-OJOHALCON' => 'partido',
            'JUG-PCOM-DOBLETE' => 'partido',
            'JUG-RAR-PLENOGARANTIZADO' => 'partido',
            'JUG-LEG-PALOMITAS' => 'mayoria',
            'JUG-LEG-CRACK' => 'jornada',
            'JUG-PCOM-AMULETO' => 'bonus',
            'JUG-LEG-AMULETO' => 'bonus',
            'JUG-COM-AMIGOARBITRO' => 'eventos',
            'JUG-COM-MADRUGADOR' => 'eventos',
            'JUG-PCOM-FILODESCANSO' => 'eventos',
            'JUG-RAR-TIEMPO' => 'eventos',
            'FAL-PCOM-ESCUDO' => 'falta',
            'FAL-COM-INVISIBLE' => 'falta',
        ];

        foreach ($esperado as $codigo => $forma) {
            $this->assertSame($forma, $servicio->formaDe($codigo), "Código {$codigo}");
        }

        $this->assertSame('falta', $servicio->formaDe('LO-QUE-SEA', 'Faltas'));
        $this->assertSame('otra', $servicio->formaDe('JUG-COM-NUEVA'));
    }

    public function test_una_liga_sin_cartas_devuelve_listas_vacias(): void
    {
        $liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'ConExtras']);
        $servicio = app(RastroCartasService::class);

        $this->assertSame([], $servicio->cartas($liga, 'jornada', 8));
        $this->assertSame([], $servicio->cartas($liga, 'mano', null));
    }
}
