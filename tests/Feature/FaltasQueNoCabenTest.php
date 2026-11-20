<?php

namespace Tests\Feature;

use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Caso real de la J14: Todo queda en casa Rara (5 locales) + Visita obligada
 * Legendaria (6 visitantes) = 11 con 10 partidos. Se ajusta la jugada más tarde.
 */
class FaltasQueNoCabenTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $victima;
    private CategoriaCarta $faltas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'ConExtras']);
        $this->victima = User::factory()->create();
        $this->faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
    }

    private function falta(string $codigo, string $jugadaEn): void
    {
        $tipo = TipoCarta::firstOrCreate(['codigo_efecto' => $codigo], [
            'id_categoria' => $this->faltas->id, 'rareza' => 'Comun', 'nombre' => $codigo, 'descripcion' => 'x', 'activa' => true,
        ]);
        CartaUsuario::forceCreate([
            'id_usuario' => User::factory()->create()->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => 12, 'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'reparto_semanal',
            'estado' => 'jugada', 'id_usuario_objetivo' => $this->victima->id, 'jornada_efecto' => 14,
            'jugada_en' => Carbon::parse($jugadaEn), 'puntos_generados' => 0,
        ]);
    }

    private function estado(): array
    {
        return app(MotorFaltas::class)->estadoRequisitoPronostico($this->liga->id, $this->victima->id, 14, collect(range(1, 10)));
    }

    private function guardar(array $despues, array $antes): ?string
    {
        return app(MotorFaltas::class)->comprobarRequisitoPronostico(
            $this->liga->id, $this->victima->id, 14, 10, count($despues), collect($despues), collect($antes)
        );
    }

    public function test_se_ajusta_la_que_se_jugo_mas_tarde_y_vuelve_a_ser_cumplible(): void
    {
        $this->falta('FAL-RAR-CASA', '2026-11-10 23:59:45');   // antes
        $this->falta('FAL-LEG-VISITA', '2026-11-19 13:00:20'); // después → baja de 6 a 5

        $estado = $this->estado();
        $porTipo = collect($estado['requisitos'])->keyBy('tipo');

        $this->assertSame(5, $porTipo['Local']['cantidad']);
        $this->assertNull($porTipo['Local']['ajustada_desde'] ?? null);
        $this->assertSame(5, $porTipo['Visitante']['cantidad']);
        $this->assertSame(6, $porTipo['Visitante']['ajustada_desde']);
        $this->assertTrue($estado['cumplible']);
        $this->assertSame(10, $estado['faltan']);
    }

    public function test_al_guardar_vale_5_y_5_pero_no_4_y_6_ni_un_empate(): void
    {
        $this->falta('FAL-RAR-CASA', '2026-11-10 23:59:45');
        $this->falta('FAL-LEG-VISITA', '2026-11-19 13:00:20');

        $l = fn ($n) => array_fill(0, $n, 'Local');
        $v = fn ($n) => array_fill(0, $n, 'Visitante');

        $this->assertNull($this->guardar([...$l(5), ...$v(5)], [...$l(5), ...$v(4)]));
        $this->assertNotNull($this->guardar([...$l(4), ...$v(6)], [...$l(4), ...$v(5)]));
        $this->assertNotNull($this->guardar(['Empate'], []));
        $this->assertStringContainsString('ajustada de 6', (string) $this->guardar(['Empate'], []));
    }

    public function test_si_la_casa_se_jugo_despues_es_ella_la_que_baja(): void
    {
        $this->falta('FAL-LEG-VISITA', '2026-11-10 10:00:00');
        $this->falta('FAL-RAR-CASA', '2026-11-19 10:00:00');

        $porTipo = collect($this->estado()['requisitos'])->keyBy('tipo');

        $this->assertSame([4, 5], [$porTipo['Local']['cantidad'], $porTipo['Local']['ajustada_desde'] ?? null]);
        $this->assertSame([6, null], [$porTipo['Visitante']['cantidad'], $porTipo['Visitante']['ajustada_desde'] ?? null]);
    }

    public function test_si_caben_no_se_toca_nada(): void
    {
        $this->falta('FAL-COM-CASA', '2026-11-10 10:00:00');
        $this->falta('FAL-COM-VISITA', '2026-11-19 10:00:00');

        foreach ($this->estado()['requisitos'] as $r) {
            $this->assertSame(3, $r['cantidad']);
            $this->assertArrayNotHasKey('ajustada_desde', $r);
        }
    }
}
