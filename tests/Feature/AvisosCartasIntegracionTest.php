<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\ConfiguracionPuntos;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Notifications\CartasResueltas;
use App\Notifications\CartasSinJugar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvisosCartasIntegracionTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $usuario;
    private CategoriaCarta $jugadas;

    protected function setUp(): void
    {
        parent::setUp();

        $temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras', 'nombre' => 'BasiLiga']);
        $this->usuario = User::factory()->create(['liga_activa_id' => $this->liga->id]);
        $this->liga->usuarios()->attach($this->usuario->id, ['rol' => 'Admin']);

        ConfiguracionPuntos::create([
            'id_liga' => $this->liga->id,
            'puntos_signo' => 1, 'puntos_diferencia' => 2, 'puntos_exacto' => 5, 'puntos_gol_goleador' => 1,
            'bonus_pleno_7' => 2, 'bonus_pleno_8' => 4, 'bonus_pleno_9' => 8, 'bonus_pleno_10' => 15,
        ]);

        $this->jugadas = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
    }

    private function partido(int $jornada, string $estado, $horario, ?int $golesCasa = null, ?int $golesFuera = null): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $this->liga->id_temporada,
            'jornada' => $jornada,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => $estado,
            'horario_estimado' => $horario,
            'goles_casa' => $golesCasa,
            'goles_fuera' => $golesFuera,
        ]);
    }

    private function chuteExtra(array $extra = []): CartaUsuario
    {
        $tipo = TipoCarta::create([
            'id_categoria' => $this->jugadas->id, 'rareza' => 'Comun', 'nombre' => 'Chute Extra',
            'descripcion' => 'test', 'codigo_efecto' => 'JUG-COM-CHUTE', 'activa' => true,
        ]);

        return CartaUsuario::create(array_merge([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => 4, 'obtenida_en' => now(), 'revelada_en' => now(),
            'origen' => 'manual', 'estado' => 'en_mano',
        ], $extra));
    }

    public function test_al_cerrar_la_jornada_se_avisa_de_lo_que_hicieron_las_cartas(): void
    {
        $partido = $this->partido(5, 'Jugado', now()->subDay(), 2, 0);

        Pronostico::create([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0, 'enviado_en' => now(),
        ]);

        $this->chuteExtra(['estado' => 'jugada', 'id_partido' => $partido->id, 'jornada_efecto' => 5, 'jugada_en' => now()]);

        $this->actingAs($this->usuario)->postJson('/api/v1/jornadas/5/cerrar')->assertOk();

        $avisos = $this->usuario->notifications()->where('type', CartasResueltas::class)->get();

        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('sumó 1 pt', $avisos->first()->data['mensaje']);
    }

    public function test_no_se_avisa_de_cartas_resueltas_a_quien_no_jugo_ninguna(): void
    {
        $partido = $this->partido(5, 'Jugado', now()->subDay(), 2, 0);

        Pronostico::create([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_partido' => $partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0, 'enviado_en' => now(),
        ]);

        $this->actingAs($this->usuario)->postJson('/api/v1/jornadas/5/cerrar')->assertOk();

        $this->assertSame(0, $this->usuario->notifications()->where('type', CartasResueltas::class)->count());
    }

    public function test_recordatorio_de_cartas_sin_jugar_llega_una_sola_vez(): void
    {
        $this->partido(6, 'Programado', now()->addHours(5));
        $this->chuteExtra();

        $this->artisan('liga:avisar-pendientes')->assertSuccessful();
        $this->artisan('liga:avisar-pendientes')->assertSuccessful(); // segunda pasada del planificador

        $this->assertSame(1, $this->usuario->notifications()->where('type', CartasSinJugar::class)->count());
    }

    public function test_no_hay_recordatorio_si_falta_mucho_para_la_jornada(): void
    {
        $this->partido(6, 'Programado', now()->addDays(3));
        $this->chuteExtra();

        $this->artisan('liga:avisar-pendientes')->assertSuccessful();

        $this->assertSame(0, $this->usuario->notifications()->where('type', CartasSinJugar::class)->count());
    }

    public function test_no_hay_recordatorio_si_no_tienes_jugadas_en_la_mano(): void
    {
        $this->partido(6, 'Programado', now()->addHours(5));

        $this->artisan('liga:avisar-pendientes')->assertSuccessful();

        $this->assertSame(0, $this->usuario->notifications()->where('type', CartasSinJugar::class)->count());
    }
}