<?php

namespace Tests\Feature;

use App\Services\VisorErroresService;
use Tests\TestCase;

class VisorErroresTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = sys_get_temp_dir().'/visor-errores-'.uniqid();
        mkdir($this->directorio);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directorio.'/*') ?: []);
        rmdir($this->directorio);

        parent::tearDown();
    }

    private function escribir(string $nombre, string $contenido): void
    {
        file_put_contents($this->directorio.'/'.$nombre, $contenido);
    }

    public function test_lee_los_errores_agrupa_los_repetidos_y_deja_fuera_lo_que_no_es_error(): void
    {
        $this->escribir('laravel-2026-10-09.log', <<<'LOG'
[2026-10-09 21:05:01] production.INFO: Partido 79 sincronizado
[2026-10-09 21:10:00] production.ERROR: Undefined array key "lineups" {"exception":"[object] (ErrorException(code: 0): Undefined array key \"lineups\" at /var/www/html/app/Services/ImportadorPartidoDetalle.php:212)
[stacktrace]
#0 /var/www/html/app/Services/ImportadorPartidoDetalle.php(212): handleError()
#1 /var/www/html/app/Jobs/Partidos/SincronizarPartidoLaligaJob.php(41): importar()
"}
[2026-10-09 21:12:00] production.WARNING: La API tardó 9 segundos
[2026-10-09 21:20:00] production.ERROR: Undefined array key "lineups" {"exception":"[object] (ErrorException(code: 0): Undefined array key \"lineups\" at /var/www/html/app/Services/ImportadorPartidoDetalle.php:212)
[stacktrace]
#0 /var/www/html/app/Services/ImportadorPartidoDetalle.php(212): handleError()
"}
[2026-10-09 22:00:00] production.ERROR: SQLSTATE[HY000]: General error: 1205 Lock wait timeout
LOG);

        $resultado = app(VisorErroresService::class)->ultimos($this->directorio, '/var/www/html');
        $errores = $resultado['errores'];

        // Dos fallos distintos, el más reciente primero; el INFO y el WARNING no entran.
        $this->assertCount(2, $errores);
        $this->assertSame('SQLSTATE[HY000]: General error: 1205 Lock wait timeout', $errores[0]['mensaje']);
        $this->assertSame(1, $errores[0]['veces']);

        $this->assertSame('Undefined array key "lineups"', $errores[1]['mensaje']);
        $this->assertSame(2, $errores[1]['veces']);
        $this->assertSame('ErrorException', $errores[1]['clase']);
        $this->assertSame('app/Services/ImportadorPartidoDetalle.php', $errores[1]['archivo']);
        $this->assertSame(212, $errores[1]['linea']);
        $this->assertStringStartsWith('2026-10-09T21:20:00', $errores[1]['cuando']);
        $this->assertStringStartsWith('2026-10-09T21:10:00', $errores[1]['primera_vez']);
        $this->assertNotEmpty($errores[1]['traza']);

        $this->assertSame('laravel-2026-10-09.log', $resultado['archivos'][0]['nombre']);
    }

    public function test_los_avisos_solo_salen_si_se_piden(): void
    {
        $this->escribir('laravel.log', "[2026-10-09 21:12:00] production.WARNING: La API tardó 9 segundos\n");
        $visor = app(VisorErroresService::class);

        $this->assertSame([], $visor->ultimos($this->directorio, '/var/www/html')['errores']);
        $this->assertCount(1, $visor->ultimos($this->directorio, '/var/www/html', 60, true)['errores']);
    }

    public function test_sin_archivos_de_registro_no_falla(): void
    {
        $this->assertSame(['errores' => [], 'archivos' => []], app(VisorErroresService::class)->ultimos($this->directorio, '/var/www/html'));
    }
}
