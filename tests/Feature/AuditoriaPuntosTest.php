<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\JornadaController;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\ConfiguracionPuntos;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditoriaPuntosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La auditoría usa las acciones reales de recalcular, dentro de una
 * transacción que siempre se deshace. Aquí se comprueba, con el cálculo de
 * verdad, que (1) una jornada bien puntuada cuadra, (2) un punto alterado se
 * detecta y (3) la base de datos queda exactamente como estaba.
 */
class AuditoriaPuntosTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $admin;
    private Request $peticion;

    protected function setUp(): void
    {
        parent::setUp();

        $temporada = Temporada::factory()->create();
        $local = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $visitante = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);

        $this->admin = User::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'Normal']);
        $this->liga->usuarios()->attach($this->admin->id, ['rol' => 'Admin', 'se_unio_en' => now()]);

        // Igual que en los demás tests de puntos: la liga necesita sus reglas de puntuación.
        ConfiguracionPuntos::create([
            'id_liga' => $this->liga->id,
            'puntos_signo' => 1, 'puntos_diferencia' => 2, 'puntos_exacto' => 5, 'puntos_gol_goleador' => 1,
            'bonus_pleno_7' => 2, 'bonus_pleno_8' => 4, 'bonus_pleno_9' => 8, 'bonus_pleno_10' => 15,
        ]);

        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'id_equipo_local' => $local->id, 'id_equipo_visitante' => $visitante->id,
            'jornada' => 7, 'estado' => 'Jugado', 'goles_casa' => 2, 'goles_fuera' => 1,
            'horario_estimado' => now()->subDays(3),
        ]);

        DB::table('pronosticos')->insert([
            'id_usuario' => $this->admin->id, 'id_liga' => $this->liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 2, 'goles_visitante_predicho' => 1,
            'enviado_en' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        CierreJornada::create(['id_liga' => $this->liga->id, 'jornada' => 7, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $this->admin->id]);

        $this->peticion = Request::create('/', 'POST');
        $this->peticion->setUserResolver(fn () => $this->admin);

        // Los puntos "guardados" se generan con el cálculo real, como en un cierre de verdad.
        $this->admin->setRelation('ligaActiva', $this->liga);
        $respuesta = app(JornadaController::class)->recalcularPuntos($this->peticion, 7);
        $this->admin->unsetRelation('ligaActiva');
        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertGreaterThan(0, EventoPuntos::count());
    }

    public function test_una_jornada_bien_puntuada_cuadra_y_no_se_toca_nada(): void
    {
        $antes = EventoPuntos::orderBy('id')->get(['id', 'puntos', 'tipo_evento'])->toArray();

        $resultado = app(AuditoriaPuntosService::class)->auditar($this->peticion, $this->liga, 7);

        $this->assertTrue($resultado['auditable']);
        $this->assertTrue($resultado['cuadra']);
        $this->assertSame([], $resultado['diferencias']);
        $this->assertSame($resultado['total_antes'], $resultado['total_despues']);

        // Mismas filas, con los mismos identificadores: no se ha borrado ni recreado nada.
        $this->assertSame($antes, EventoPuntos::orderBy('id')->get(['id', 'puntos', 'tipo_evento'])->toArray());
        $this->assertNull(CierreJornada::first()->goleadores_calculados_en);
    }

    public function test_un_punto_alterado_se_detecta_y_sigue_alterado_despues(): void
    {
        $evento = EventoPuntos::where('tipo_evento', 'AciertoExacto')->firstOrFail();
        $correcto = (int) $evento->puntos;
        $evento->update(['puntos' => $correcto + 5]);

        $resultado = app(AuditoriaPuntosService::class)->auditar($this->peticion, $this->liga, 7);

        $this->assertTrue($resultado['auditable']);
        $this->assertFalse($resultado['cuadra']);
        $this->assertCount(1, $resultado['diferencias']);
        $this->assertSame(5, $resultado['diferencias'][0]['antes'] - $resultado['diferencias'][0]['despues']);
        $this->assertSame('Pronóstico de Sevilla - Rayo', $resultado['diferencias'][0]['detalle'][0]['concepto']);

        // La auditoría solo mira: el valor alterado sigue ahí hasta que alguien recalcule de verdad.
        $this->assertSame($correcto + 5, (int) $evento->fresh()->puntos);
    }

    public function test_una_jornada_sin_cerrar_no_se_puede_auditar(): void
    {
        $resultado = app(AuditoriaPuntosService::class)->auditar($this->peticion, $this->liga, 8);

        $this->assertFalse($resultado['auditable']);
        $this->assertNotNull($resultado['motivo']);
    }
}
