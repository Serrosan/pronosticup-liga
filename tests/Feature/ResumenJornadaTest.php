<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ResumenJornadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El resumen de una jornada cerrada: solo lee lo que el cierre dejó guardado.
 */
class ResumenJornadaTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Liga $liga;
    private Equipo $local;
    private Equipo $visitante;
    /** @var array<string,User> */
    private array $u = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => 'ConExtras']);
        $this->local = Equipo::factory()->create(['nombre' => 'Real Betis', 'nombre_corto' => 'Betis']);
        $this->visitante = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);

        foreach (['Ana', 'Bea', 'Carlos', 'Dani', 'Eva'] as $nombre) {
            $usuario = User::factory()->create(['name' => $nombre]);
            $this->liga->usuarios()->attach($usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
            $this->u[$nombre] = $usuario;
        }
    }

    private function cerrar(int $jornada): void
    {
        CierreJornada::create(['id_liga' => $this->liga->id, 'jornada' => $jornada, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $this->u['Ana']->id]);
    }

    private function puntos(string $quien, int $jornada, int $puntos, string $tipo = 'GolesGoleadorElegido', ?int $idPartido = null): void
    {
        EventoPuntos::create([
            'id_usuario' => $this->u[$quien]->id,
            'id_liga' => $this->liga->id,
            'id_partido' => $idPartido,
            'jornada' => $jornada,
            'tipo_evento' => $tipo,
            'puntos' => $puntos,
        ]);
    }

    private function partido(int $golesCasa, int $golesFuera): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->local->id,
            'id_equipo_visitante' => $this->visitante->id,
            'jornada' => 7,
            'estado' => 'Jugado',
            'horario_estimado' => now()->subDays(2),
            'goles_casa' => $golesCasa,
            'goles_fuera' => $golesFuera,
        ]);
    }

    private function pronostico(string $quien, CalendarioPartido $partido, int $local, int $visitante, string $tipo, int $puntos): void
    {
        Pronostico::create([
            'id_usuario' => $this->u[$quien]->id,
            'id_liga' => $this->liga->id,
            'id_partido' => $partido->id,
            'resultado_1x2' => $local > $visitante ? 'Local' : ($local < $visitante ? 'Visitante' : 'Empate'),
            'goles_local_predicho' => $local,
            'goles_visitante_predicho' => $visitante,
            'enviado_en' => now(),
        ]);
        $this->puntos($quien, 7, $puntos, $tipo, $partido->id);
    }

    private function resumen(string $yo = 'Ana'): ?array
    {
        return app(ResumenJornadaService::class)->resumen($this->liga->fresh(), 7, $this->u[$yo]);
    }

    public function test_sin_cerrar_no_hay_resumen(): void
    {
        $this->puntos('Ana', 7, 5);

        $this->assertNull($this->resumen());
    }

    public function test_podio_farolillo_y_movimientos_en_la_clasificacion(): void
    {
        foreach (['Ana' => 10, 'Bea' => 8, 'Carlos' => 6, 'Dani' => 4, 'Eva' => 2] as $quien => $p) {
            $this->puntos($quien, 6, $p);
        }
        foreach (['Ana' => 0, 'Bea' => 1, 'Carlos' => 3, 'Dani' => 7, 'Eva' => 12] as $quien => $p) {
            $this->puntos($quien, 7, $p);
        }
        $this->cerrar(7);

        $r = $this->resumen();

        $this->assertSame(['Eva', 'Dani', 'Carlos'], array_column($r['podio'], 'usuario'));
        $this->assertSame([12, 7, 3], array_column($r['podio'], 'puntos'));
        $this->assertSame(['Ana'], array_column($r['farolillo'], 'usuario'));
        $this->assertTrue($r['farolillo'][0]['soy_yo']);

        // Antes: Ana 1ª … Eva 5ª. Después: Eva 14, Dani 11, Ana 10, Bea 9, Carlos 9.
        $this->assertSame('Eva', $r['sube']['usuario']);
        $this->assertSame(4, $r['sube']['puestos']);
        $this->assertSame(1, $r['sube']['puesto']);
        $this->assertSame('Ana', $r['baja']['usuario']);
        $this->assertSame(2, $r['baja']['puestos']);
        $this->assertSame('Eva', $r['lider']['usuario']);
        $this->assertTrue($r['lider']['nuevo']);
    }

    public function test_exacto_que_nadie_mas_vio_y_el_que_fue_contra_todos_y_acerto(): void
    {
        $betis = $this->partido(2, 1);
        $this->pronostico('Ana', $betis, 2, 1, 'AciertoExacto', 5);
        $this->pronostico('Bea', $betis, 1, 0, 'Acierto1x2', 1);
        $this->pronostico('Carlos', $betis, 1, 0, 'Acierto1x2', 1);
        $this->pronostico('Dani', $betis, 0, 0, 'Fallo', 0);   // solo con la X, pero falla: no cuenta
        $this->pronostico('Eva', $betis, 0, 2, 'Fallo', 0);

        $otro = $this->partido(0, 1);
        $this->pronostico('Ana', $otro, 2, 0, 'Fallo', 0);
        $this->pronostico('Bea', $otro, 1, 0, 'Fallo', 0);
        $this->pronostico('Carlos', $otro, 2, 1, 'Fallo', 0);
        $this->pronostico('Dani', $otro, 3, 1, 'Fallo', 0);
        $this->pronostico('Eva', $otro, 0, 1, 'AciertoExacto', 5); // la única con el 2, y lo clava
        $this->cerrar(7);

        $r = $this->resumen();

        $this->assertEqualsCanonicalizing(['Ana', 'Eva'], array_column($r['exactos_unicos'], 'usuario'));
        $this->assertCount(1, $r['contra_todos']);
        $this->assertSame('Eva', $r['contra_todos'][0]['usuario']);
        $this->assertSame('Visitante', $r['contra_todos'][0]['signo']);
        $this->assertSame(4, $r['contra_todos'][0]['contra']);
        $this->assertNull($r['mas_exactos'], 'Un exacto cada una no es noticia');
        $this->assertNull($r['mejor_carta']);
        $this->assertNull($r['goleador']);
    }
}
