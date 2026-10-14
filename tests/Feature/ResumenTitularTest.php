<?php

namespace Tests\Feature;

use App\Services\ResumenJornadaService;
use Tests\TestCase;

/**
 * El titular de la Novedad que se publica al cerrar una jornada.
 */
class ResumenTitularTest extends TestCase
{
    private function persona(string $nombre, array $extra = []): array
    {
        return ['id_usuario' => crc32($nombre), 'usuario' => $nombre, 'avatar_url' => null, 'soy_yo' => false] + $extra;
    }

    private function resumenCompleto(): array
    {
        return [
            'podio' => [
                $this->persona('Eva', ['puntos' => 12, 'posicion' => 1]),
                $this->persona('Dani', ['puntos' => 12, 'posicion' => 1]),
                $this->persona('Ana', ['puntos' => 7, 'posicion' => 3]),
            ],
            'farolillo' => [],
            'media' => 6.5,
            'lider' => $this->persona('Eva', ['nuevo' => true]),
            'sube' => $this->persona('Dani', ['puestos' => 3, 'puesto' => 2]),
            'baja' => null,
            'mas_exactos' => null,
            'exactos_unicos' => [$this->persona('Ana', ['partido' => 'Betis - Sevilla', 'marcador' => '3-2', 'pronosticaron' => 9])],
            'contra_todos' => [$this->persona('Bea', ['partido' => 'Rayo - Athletic', 'signo' => 'Empate', 'contra' => 8])],
            'mejor_carta' => $this->persona('Pedro', ['carta' => 'Doblete', 'puntos' => 4, 'partido' => null]),
            'goleador' => null,
        ];
    }

    public function test_cuenta_lo_mas_comentado_en_una_linea(): void
    {
        $titular = app(ResumenJornadaService::class)->titular($this->resumenCompleto(), 8);

        $this->assertStringStartsWith('📊 Jornada 8 cerrada: 🥇 Eva y Dani ganan con 12pt', $titular);
        $this->assertStringContainsString('👑 Eva se pone líder', $titular);
        $this->assertStringContainsString('🎯 Ana clava el 3-2 del Betis - Sevilla', $titular);
        $this->assertLessThanOrEqual(ResumenJornadaService::LARGO_TITULAR, mb_strlen($titular));
    }

    public function test_nunca_se_pasa_de_largo_y_corta_por_el_final(): void
    {
        $resumen = $this->resumenCompleto();
        $resumen['exactos_unicos'][0]['partido'] = str_repeat('Equipo con nombre larguísimo ', 6).'- Otro';

        $titular = app(ResumenJornadaService::class)->titular($resumen, 8);

        $this->assertLessThanOrEqual(ResumenJornadaService::LARGO_TITULAR, mb_strlen($titular));
        $this->assertStringContainsString('🥇 Eva y Dani ganan', $titular, 'Lo primero siempre entra');
        $this->assertStringNotContainsString('🎯', $titular, 'Lo que no cabe no sale, ni a medias');
    }

    public function test_sin_nada_destacable_queda_el_titular_sencillo(): void
    {
        $vacio = ['podio' => [], 'lider' => null, 'sube' => null, 'exactos_unicos' => [], 'contra_todos' => [], 'mejor_carta' => null];

        $this->assertSame('📊 Jornada 3 cerrada', app(ResumenJornadaService::class)->titular($vacio, 3));
    }
}
