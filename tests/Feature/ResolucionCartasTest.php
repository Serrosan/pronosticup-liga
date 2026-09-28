<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\ConfiguracionPuntos;
use App\Models\Equipo;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Services\MotorEfectosCartas;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolucionCartasTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $usuario;
    private CalendarioPartido $partido;
    private ConfiguracionPuntos $config;
    private CategoriaCarta $jugadas;
    private CategoriaCarta $faltas;

    protected function setUp(): void
    {
        parent::setUp();

        $temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras']);
        $this->usuario = User::factory()->create();

        $this->partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'jornada' => 5,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => 'Jugado',
            'goles_casa' => 2,
            'goles_fuera' => 0,
        ]);

        $this->config = ConfiguracionPuntos::create([
            'id_liga' => $this->liga->id,
            'puntos_signo' => 1, 'puntos_diferencia' => 2, 'puntos_exacto' => 5, 'puntos_gol_goleador' => 1,
            'bonus_pleno_7' => 2, 'bonus_pleno_8' => 4, 'bonus_pleno_9' => 8, 'bonus_pleno_10' => 15,
        ]);

        $this->jugadas = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
        $this->faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
    }

    private function tipo(CategoriaCarta $categoria, string $nombre, string $codigo, string $rareza = 'Comun'): TipoCarta
    {
        return TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => $rareza, 'nombre' => $nombre,
            'descripcion' => 'test', 'codigo_efecto' => $codigo, 'activa' => true,
        ]);
    }

    private function carta(TipoCarta $tipo, array $extra = []): CartaUsuario
    {
        return CartaUsuario::create(array_merge([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => 4, 'obtenida_en' => now(), 'revelada_en' => now(),
            'origen' => 'manual', 'estado' => 'jugada', 'jugada_en' => now(),
        ], $extra));
    }

    private function ajustar(int $puntosBase = 1, string $tipoReal = 'Acierto1x2'): array
    {
        return app(MotorEfectosCartas::class)->ajustarPuntosPartido(
            $this->liga->id, $this->usuario->id, $this->partido->id, $puntosBase, $tipoReal, 'Local', $this->config
        );
    }

    public function test_recalcular_no_pierde_el_bonus_de_una_carta_ya_resuelta(): void
    {
        $chute = $this->carta($this->tipo($this->jugadas, 'Chute Extra', 'JUG-COM-CHUTE'), [
            'id_partido' => $this->partido->id, 'jornada_efecto' => 5,
        ]);

        $primera = $this->ajustar();
        $segunda = $this->ajustar(); // = recalcular la jornada

        $this->assertSame(2, $primera['puntos']);
        $this->assertSame(2, $segunda['puntos']);

        $chute->refresh();
        $this->assertSame('resuelta_cumplida', $chute->estado);
        $this->assertSame(1, $chute->puntos_generados);
    }

    public function test_varias_cartas_sobre_el_mismo_partido_se_aplican_en_orden_de_juego(): void
    {
        $this->carta($this->tipo($this->jugadas, 'Chute Extra', 'JUG-COM-CHUTE'), [
            'id_partido' => $this->partido->id, 'jornada_efecto' => 5, 'jugada_en' => now()->subMinute(),
        ]);
        $this->carta($this->tipo($this->jugadas, 'Doblete', 'JUG-PCOM-DOBLETE', 'PocoComun'), [
            'id_partido' => $this->partido->id, 'jornada_efecto' => 5, 'jugada_en' => now(),
        ]);

        // base 1 -> Chute Extra (+1) = 2 -> Doblete (x2) = 4
        $this->assertSame(4, $this->ajustar()['puntos']);
        $this->assertSame(4, $this->ajustar()['puntos']); // y recalcular da lo mismo
    }

    public function test_una_carta_que_no_aporta_nada_queda_como_no_cumplida(): void
    {
        $ojo = $this->carta($this->tipo($this->jugadas, 'Ojo de Halcón', 'JUG-COM-OJOHALCON'), [
            'id_partido' => $this->partido->id, 'jornada_efecto' => 5,
        ]);

        $this->assertSame(1, $this->ajustar(1, 'Acierto1x2')['puntos']); // no fue exacto

        $this->assertSame('resuelta_no_cumplida', $ojo->fresh()->estado);
    }

    public function test_un_amuleto_de_otra_jornada_no_se_gasta_en_esta(): void
    {
        $motor = app(MotorEfectosCartas::class);
        $amuleto = $this->carta($this->tipo($this->jugadas, 'Amuleto', 'JUG-RAR-AMULETO', 'Rara'), [
            'jornada_efecto' => 6, // jugado para la jornada SIGUIENTE
        ]);

        $aciertos = $motor->ajustarAciertosParaBonus($this->liga->id, $this->usuario->id, 5, 9, collect([101]));

        $this->assertSame(9, $aciertos);
        $this->assertSame('jugada', $amuleto->fresh()->estado);
    }

    public function test_amuleto_automatico_no_pide_elegir_partido_pero_el_elegido_si(): void
    {
        $motor = app(MotorEfectosCartas::class);

        $this->assertFalse($motor->requiereEleccionDePartido('JUG-RAR-AMULETO'));
        $this->assertFalse($motor->requiereEleccionDePartido('JUG-LEG-AMULETO'));
        $this->assertFalse($motor->requiereEleccionDePartido('JUG-LEG-CRACK'));
        $this->assertTrue($motor->requiereEleccionDePartido('JUG-PCOM-AMULETO'));
        $this->assertTrue($motor->requiereEleccionDePartido('JUG-COM-CHUTE'));
    }

    public function test_el_barrido_resuelve_lo_pendiente_y_respeta_lo_demas(): void
    {
        $silbato = $this->carta($this->tipo($this->faltas, 'Silbato', 'FAL-COM-SILBATO'), ['jornada_efecto' => 5, 'id_usuario_objetivo' => $this->usuario->id]);
        $silbatoFuturo = $this->carta($this->tipo($this->faltas, 'Silbato', 'FAL-COM-SILBATO'), ['jornada_efecto' => 6, 'id_usuario_objetivo' => $this->usuario->id]);
        $escudo = $this->carta($this->tipo($this->faltas, 'Escudo', 'FAL-PCOM-ESCUDO', 'PocoComun'), ['jornada_efecto' => 5, 'id_usuario_objetivo' => $this->usuario->id]);
        $chuteSinPronostico = $this->carta($this->tipo($this->jugadas, 'Chute Extra', 'JUG-COM-CHUTE'), ['id_partido' => $this->partido->id, 'jornada_efecto' => 5]);
        $madrugador = $this->carta($this->tipo($this->jugadas, 'Madrugador', 'JUG-COM-MADRUGADOR'), ['id_partido' => $this->partido->id, 'jornada_efecto' => 5]);

        app(MotorEfectosCartas::class)->cerrarCartasPendientes($this->liga->id, 5);
        app(MotorFaltas::class)->resolverFaltasDeLaJornada($this->liga->id, 5);

        $this->assertSame('resuelta_cumplida', $silbato->fresh()->estado);
        $this->assertSame('jugada', $silbatoFuturo->fresh()->estado);            // efecto en la jornada 6
        $this->assertSame('resuelta_no_cumplida', $escudo->fresh()->estado);     // Escudo sin gastar
        $this->assertSame('resuelta_no_cumplida', $chuteSinPronostico->fresh()->estado);
        $this->assertSame('jugada', $madrugador->fresh()->estado);               // espera a los eventos
    }

    public function test_una_falta_contra_un_escudo_pierde_las_dos_cartas(): void
    {
        $this->usuario->update(['liga_activa_id' => $this->liga->id]);
        $victima = User::factory()->create();
        $this->liga->usuarios()->attach($this->usuario->id, ['rol' => 'Miembro']);
        $this->liga->usuarios()->attach($victima->id, ['rol' => 'Miembro']);
        $this->liga->usuarios()->attach(User::factory()->create()->id, ['rol' => 'Miembro']);
        $this->liga->usuarios()->attach(User::factory()->create()->id, ['rol' => 'Miembro']);

        CalendarioPartido::factory()->create([
            'id_temporada' => $this->liga->id_temporada,
            'jornada' => 6,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => 'Programado',
            'horario_estimado' => now()->addDays(3),
        ]);

        $escudo = $this->carta($this->tipo($this->faltas, 'Escudo', 'FAL-PCOM-ESCUDO', 'PocoComun'), [
            'id_usuario' => $victima->id, 'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 7,
        ]);

        $silbato = $this->carta($this->tipo($this->faltas, 'Silbato', 'FAL-COM-SILBATO'), [
            'estado' => 'en_mano', 'jugada_en' => null,
        ]);

        $respuesta = $this->actingAs($this->usuario)->postJson("/api/v1/mis-cartas/{$silbato->id}/jugar-falta", [
            'id_usuario_objetivo' => $victima->id,
        ]);

        $respuesta->assertStatus(422);
        $this->assertSame('resuelta_no_cumplida', $silbato->fresh()->estado);
        $this->assertSame('resuelta_cumplida', $escudo->fresh()->estado);
    }

    public function test_sin_comodines_bloquea_tambien_al_amuleto_automatico(): void
    {
        $this->usuario->update(['liga_activa_id' => $this->liga->id]);

        // Próxima jornada jugable: la 6 (la 5 ya está Jugada)
        CalendarioPartido::factory()->create([
            'id_temporada' => $this->liga->id_temporada,
            'jornada' => 6,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => 'Programado',
            'horario_estimado' => now()->addDays(3),
        ]);

        $atacante = User::factory()->create();
        $this->carta($this->tipo($this->faltas, 'Sin Comodines', 'FAL-PCOM-SINCOMODINES', 'PocoComun'), [
            'id_usuario' => $atacante->id, 'id_usuario_objetivo' => $this->usuario->id, 'jornada_efecto' => 6,
        ]);

        $amuleto = $this->carta($this->tipo($this->jugadas, 'Amuleto', 'JUG-RAR-AMULETO', 'Rara'), [
            'estado' => 'en_mano', 'jugada_en' => null,
        ]);

        $this->actingAs($this->usuario)->postJson("/api/v1/mis-cartas/{$amuleto->id}/jugar")->assertStatus(422);

        $this->assertSame('en_mano', $amuleto->fresh()->estado);
    }

    public function test_no_se_puede_jugar_una_carta_generica_con_la_jornada_ya_empezada(): void
    {
        $this->usuario->update(['liga_activa_id' => $this->liga->id]);

        CalendarioPartido::factory()->create([
            'id_temporada' => $this->liga->id_temporada,
            'jornada' => 6,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => 'En juego',
            'horario_estimado' => now()->subHour(),
        ]);

        $crack = $this->carta($this->tipo($this->jugadas, 'Crack', 'JUG-LEG-CRACK', 'Legendaria'), [
            'estado' => 'en_mano', 'jugada_en' => null,
        ]);

        $this->actingAs($this->usuario)->postJson("/api/v1/mis-cartas/{$crack->id}/jugar")->assertStatus(422);

        $this->assertSame('en_mano', $crack->fresh()->estado);
    }

    public function test_la_proxima_jornada_jugable_informa_del_plazo(): void
    {
        $this->usuario->update(['liga_activa_id' => $this->liga->id]);

        CalendarioPartido::factory()->create([
            'id_temporada' => $this->liga->id_temporada,
            'jornada' => 6,
            'id_equipo_local' => Equipo::factory()->create()->id,
            'id_equipo_visitante' => Equipo::factory()->create()->id,
            'estado' => 'Programado',
            'horario_estimado' => now()->addDays(3),
        ]);

        $datos = $this->actingAs($this->usuario)->getJson('/api/v1/mis-cartas/proxima-jornada-jugable')->assertOk()->json('data');

        $this->assertSame(6, $datos['jornada']);
        $this->assertFalse($datos['bloqueada']);
        $this->assertNotNull($datos['cierra_en']);
        $this->assertCount(1, $datos['partidos']);
    }

    public function test_mis_pronosticos_suma_los_bonus_de_cartas_sin_pisar_los_puntos_del_partido(): void
    {
        $this->usuario->update(['liga_activa_id' => $this->liga->id]);

        Pronostico::create([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_partido' => $this->partido->id,
            'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0, 'enviado_en' => now(),
        ]);

        $comun = ['id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'jornada' => 5];
        EventoPuntos::create($comun + ['id_partido' => $this->partido->id, 'tipo_evento' => 'Acierto1x2', 'puntos' => 1]);
        EventoPuntos::create($comun + ['id_partido' => $this->partido->id, 'tipo_evento' => 'CartaEventoPartido', 'puntos' => 2]);
        EventoPuntos::create($comun + ['id_partido' => null, 'tipo_evento' => 'CartaBonoJornada', 'puntos' => 4]);

        $this->carta($this->tipo($this->jugadas, 'Amigo del Árbitro', 'JUG-COM-AMIGOARBITRO'), [
            'id_partido' => $this->partido->id, 'jornada_efecto' => 5, 'estado' => 'resuelta_cumplida', 'puntos_generados' => 2,
        ]);

        $datos = $this->actingAs($this->usuario)->getJson('/api/v1/pronosticos')->assertOk()->json('data');
        $fila = $datos['jornadas'][0]['partidos'][0];

        $this->assertSame('Acierto1x2', $fila['tipo_evento']);   // el evento de la carta no pisa al del resultado
        $this->assertSame(3, $fila['puntos']);                   // 1 del resultado + 2 de la carta
        $this->assertCount(1, $fila['cartas']);
        $this->assertSame(4, $datos['jornadas'][0]['bonus_cartas']);
        $this->assertSame(7, $datos['jornadas'][0]['puntos_totales_jornada']);
        $this->assertSame(7, $datos['stats']['puntos_totales']);
    }
}