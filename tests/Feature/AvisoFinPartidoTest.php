<?php

namespace Tests\Feature;

use App\Jobs\Partidos\AvisarFinDePartidoJob;
use App\Jobs\Partidos\SincronizarPartidoLaligaJob;
use App\Jobs\Partidos\SincronizarPartidosJob;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\PushSuscripcion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AvisosPush;
use App\Services\FootballDataService;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AvisoFinPartidoTest extends TestCase
{
    use RefreshDatabase;

    /** Avisos que se habrían mandado: [id_usuario, titulo, cuerpo, url]. */
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PushService::class, function ($m) {
            $m->shouldReceive('configurado')->andReturn(true);
            $m->shouldReceive('enviarA')->andReturnUsing(function ($usuario, $titulo, $cuerpo, $url) {
                $this->enviados[] = [$usuario->id, $titulo, $cuerpo, $url];

                return ['enviados' => 1, 'caducadas' => 0, 'fallidos' => 0];
            });
        });
    }

    private function usuario(Liga $liga, bool $quiereAvisos = true, bool $conMovil = true): User
    {
        $usuario = User::factory()->create(['liga_activa_id' => $liga->id]);
        $usuario->forceFill(['preferencias_push' => ['partidos' => $quiereAvisos]])->save();
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Miembro']);

        if ($conMovil) {
            $endpoint = 'https://push.example.com/'.$usuario->id;
            PushSuscripcion::create([
                'id_usuario' => $usuario->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSuscripcion::huella($endpoint),
                'p256dh' => 'clave', 'auth' => 'auth',
            ]);
        }

        return $usuario;
    }

    private function pronostico(User $usuario, Liga $liga, CalendarioPartido $partido, int $local, int $visitante): void
    {
        Pronostico::create([
            'id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => $local > $visitante ? 'Local' : ($local < $visitante ? 'Visitante' : 'Empate'),
            'goles_local_predicho' => $local, 'goles_visitante_predicho' => $visitante,
        ]);
    }

    /** @return array{0: Temporada, 1: Liga, 2: CalendarioPartido} */
    private function escenario(): array
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'nombre' => 'BasiLiga']);
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 10, 'estado' => 'Jugado',
            'goles_casa' => 0, 'goles_fuera' => 0,
            'id_equipo_local' => Equipo::factory()->create(['nombre' => 'RC Celta', 'nombre_corto' => 'Celta']),
            'id_equipo_visitante' => Equipo::factory()->create(['nombre' => 'Real Betis', 'nombre_corto' => 'Betis']),
        ]);

        return [$temporada, $liga, $partido];
    }

    private function avisosDe(User $usuario): array
    {
        return array_values(array_filter($this->enviados, fn ($e) => $e[0] === $usuario->id));
    }

    public function test_avisa_con_el_resultado_y_lo_que_pronosticaste(): void
    {
        [, $liga, $partido] = $this->escenario();
        $jugador = $this->usuario($liga);
        $this->pronostico($jugador, $liga, $partido, 1, 1);
        $sinPronostico = $this->usuario($liga);

        AvisarFinDePartidoJob::dispatchSync($partido->id);

        $this->assertSame([[$jugador->id, '⚽ Final: Celta 0-0 Betis', 'Tu pronóstico: 1-1 · ≈ Diferencia', '/jornadas/10']], $this->avisosDe($jugador));
        $this->assertSame('No lo pronosticaste', $this->avisosDe($sinPronostico)[0][2]);
    }

    public function test_solo_a_quien_lo_tiene_activado_y_con_movil(): void
    {
        [, $liga, $partido] = $this->escenario();
        $noQuiere = $this->usuario($liga, quiereAvisos: false);
        $sinMovil = $this->usuario($liga, conMovil: false);
        $porDefecto = $this->usuario($liga);
        $porDefecto->forceFill(['preferencias_push' => []])->save();

        AvisarFinDePartidoJob::dispatchSync($partido->id);

        $this->assertSame([], $this->enviados, 'Viene desactivado de partida y sin móvil no hay a dónde mandarlo');
    }

    public function test_con_dos_ligas_y_pronosticos_distintos_sale_una_linea_por_liga(): void
    {
        [$temporada, $liga, $partido] = $this->escenario();
        $familia = Liga::factory()->create(['id_temporada' => $temporada->id, 'nombre' => 'Liga Familia']);
        $jugador = $this->usuario($liga);
        $familia->usuarios()->attach($jugador->id, ['rol' => 'Miembro']);
        $this->pronostico($jugador, $liga, $partido, 0, 0);
        $this->pronostico($jugador, $familia, $partido, 2, 1);

        AvisarFinDePartidoJob::dispatchSync($partido->id);

        $avisos = $this->avisosDe($jugador);
        $this->assertCount(1, $avisos, 'Un solo aviso aunque esté en dos ligas');
        $this->assertStringContainsString('BasiLiga: 0-0 · ★ Exacto', $avisos[0][2]);
        $this->assertStringContainsString('Liga Familia: 2-1 · ✕ Fallo', $avisos[0][2]);
    }

    public function test_no_se_repite_si_el_job_se_lanza_dos_veces(): void
    {
        [, $liga, $partido] = $this->escenario();
        $jugador = $this->usuario($liga);

        AvisarFinDePartidoJob::dispatchSync($partido->id);
        AvisarFinDePartidoJob::dispatchSync($partido->id);

        $this->assertCount(1, $this->avisosDe($jugador));
    }

    public function test_el_tipo_de_acierto_sigue_las_reglas_del_juego(): void
    {
        $this->assertSame('exacto', AvisarFinDePartidoJob::tipoDeAcierto(2, 1, 2, 1));
        $this->assertSame('diferencia', AvisarFinDePartidoJob::tipoDeAcierto(3, 2, 2, 1));
        $this->assertSame('signo', AvisarFinDePartidoJob::tipoDeAcierto(3, 0, 2, 1));
        $this->assertSame('fallo', AvisarFinDePartidoJob::tipoDeAcierto(0, 1, 2, 1));
        $this->assertSame('diferencia', AvisarFinDePartidoJob::tipoDeAcierto(1, 1, 0, 0)); // empate cercano
        $this->assertSame('signo', AvisarFinDePartidoJob::tipoDeAcierto(3, 3, 0, 0));
    }

    public function test_la_categoria_sale_en_el_perfil_y_viene_apagada(): void
    {
        $usuario = User::factory()->create();
        $categorias = collect(app(AvisosPush::class)->paraPerfil($usuario))->keyBy('clave');

        $this->assertTrue($categorias->has('partidos'));
        $this->assertFalse($categorias['partidos']['activa']);
    }

    public function test_la_sincronizacion_lanza_el_aviso_al_terminar_el_partido(): void
    {
        Bus::fake([AvisarFinDePartidoJob::class, SincronizarPartidoLaligaJob::class]);
        [, , $partido] = $this->escenario();
        $partido->forceFill(['estado' => 'En juego', 'id_partido_api' => 999001, 'horario_estimado' => now()->subHours(2)])->save();

        $this->mock(FootballDataService::class, fn ($m) => $m->shouldReceive('obtenerJornada')->andReturn([[
            'id' => 999001, 'status' => 'FINISHED', 'referees' => [],
            'score' => ['fullTime' => ['home' => 0, 'away' => 0]],
        ]]));

        (new SincronizarPartidosJob)->handle(app(FootballDataService::class));
        (new SincronizarPartidosJob)->handle(app(FootballDataService::class)); // la siguiente pasada ya no es "acaba de terminar"

        Bus::assertDispatchedTimes(AvisarFinDePartidoJob::class, 1);
        Bus::assertDispatched(AvisarFinDePartidoJob::class, fn ($job) => $job->idPartido === $partido->id);
    }
}
