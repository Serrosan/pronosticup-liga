<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Notifications\JornadaAbierta;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ligas con cartas: la jornada 10 no se puede rellenar hasta que acaba la 9
 * (así ya están jugadas todas las Faltas contra la 10). Las ligas sin cartas
 * no cambian. Y quien ya la tenía rellena puede corregirla partido a partido.
 */
class AperturaJornadasTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private CalendarioPartido $ultimoDeLa9;
    private CalendarioPartido $partidoDeLa10;
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        [$a, $b] = [Equipo::factory()->create(), Equipo::factory()->create()];
        $partido = fn (int $jornada, string $estado, $horario) => CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'jornada' => $jornada, 'estado' => $estado, 'horario_estimado' => $horario,
            'id_equipo_local' => $a->id, 'id_equipo_visitante' => $b->id,
            'goles_casa' => $estado === 'Jugado' ? 1 : null, 'goles_fuera' => $estado === 'Jugado' ? 0 : null,
        ]);

        foreach (range(1, 3) as $_) $partido(9, 'Jugado', now()->subDays(2));
        $this->ultimoDeLa9 = $partido(9, 'Programado', now()->addHours(3));
        $this->partidoDeLa10 = $partido(10, 'Programado', now()->addDays(4));
        $partido(10, 'Programado', now()->addDays(4));

        $this->ana = User::factory()->create();
    }

    private function enLiga(string $tipo): Liga
    {
        $liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => $tipo]);
        $liga->usuarios()->attach($this->ana->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
        $this->ana->forceFill(['liga_activa_id' => $liga->id])->save();
        Sanctum::actingAs($this->ana->fresh());

        return $liga;
    }

    private function pronosticarLa10()
    {
        return $this->postJson('/api/v1/pronosticos', ['id_partido' => $this->partidoDeLa10->id, 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 2]);
    }

    public function test_con_cartas_la_10_no_se_abre_hasta_que_acaba_la_9(): void
    {
        $this->enLiga('ConExtras');

        $this->pronosticarLa10()->assertStatus(422)->assertJsonFragment(['message' => app(\App\Services\AperturaJornadas::class)->motivoCerrada($this->ana->fresh()->ligaActiva, 10)]);
        $this->postJson('/api/v1/jornadas/10/goleadores', ['jugadores' => [Jugador::factory()->create()->id]])->assertStatus(422);
        $this->getJson('/api/v1/jornadas/10/partidos')->assertOk()->assertJsonPath('meta.jornada_abierta', false);

        $this->ultimoDeLa9->update(['estado' => 'Jugado', 'goles_casa' => 2, 'goles_fuera' => 2]);

        $this->pronosticarLa10()->assertSuccessful();
        $this->getJson('/api/v1/jornadas/10/partidos')->assertJsonPath('meta.jornada_abierta', true);
    }

    public function test_sin_cartas_se_puede_pronosticar_con_antelacion(): void
    {
        $this->enLiga('Normal');

        $this->pronosticarLa10()->assertSuccessful();
    }

    public function test_al_abrirse_la_jornada_se_avisa_una_sola_vez(): void
    {
        $this->enLiga('ConExtras');
        $this->artisan('liga:avisar-pendientes')->assertSuccessful();
        $this->assertSame(0, $this->ana->notifications()->where('type', JornadaAbierta::class)->count());

        $this->ultimoDeLa9->update(['estado' => 'Jugado', 'goles_casa' => 0, 'goles_fuera' => 0]);
        $this->artisan('liga:avisar-pendientes')->assertSuccessful();
        $this->artisan('liga:avisar-pendientes')->assertSuccessful();

        $avisos = $this->ana->notifications()->where('type', JornadaAbierta::class)->get();
        $this->assertCount(1, $avisos);
        $this->assertSame(10, (int) $avisos->first()->data['jornada']);
    }

    public function test_con_la_jornada_rellena_y_una_falta_nueva_se_puede_corregir_partido_a_partido(): void
    {
        $liga = $this->enLiga('ConExtras');
        $categoria = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $visita = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Rara', 'nombre' => 'Visita Obligada',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-RAR-VISITA', 'activa' => true,
        ]);
        CartaUsuario::create([
            'id_usuario' => User::factory()->create()->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $visita->id,
            'jornada_obtenida' => 9, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $this->ana->id, 'jornada_efecto' => 10, 'jugada_en' => now(),
        ]);

        $motor = app(MotorFaltas::class);
        // 10 partidos, todos rellenos: 2 visitantes y 8 locales; hacen falta 4 visitantes.
        $antes = collect(array_merge(array_fill(0, 2, 'Visitante'), array_fill(0, 8, 'Local')));

        // Cambiar un local a visitante (3 de 4): acerca → se deja.
        $acerca = collect(array_merge(array_fill(0, 3, 'Visitante'), array_fill(0, 7, 'Local')));
        $this->assertNull($motor->comprobarRequisitoPronostico($liga->id, $this->ana->id, 10, 10, 10, $acerca, $antes));

        // Cambiar un local por otro local (sigue 2 de 4): no empeora → se deja.
        $this->assertNull($motor->comprobarRequisitoPronostico($liga->id, $this->ana->id, 10, 10, 10, $antes, $antes));

        // Cambiar un visitante a local (1 de 4): aleja → no se deja.
        $aleja = collect(array_merge(['Visitante'], array_fill(0, 9, 'Local')));
        $this->assertNotNull($motor->comprobarRequisitoPronostico($liga->id, $this->ana->id, 10, 10, 10, $aleja, $antes));
    }
}
