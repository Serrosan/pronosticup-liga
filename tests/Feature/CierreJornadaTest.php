<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use App\Services\CierreJornadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La lista de cierre de jornada y la regla de "próxima jornada" que ignora
 * los partidos aplazados lejos de su jornada (caso real: uno de la jornada 6
 * recolocado el 21 de octubre, entre la 9 y la 10).
 */
class CierreJornadaTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $temporada;
    private Equipo $local;
    private Equipo $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->local = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $this->visitante = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);
    }

    private function partido(int $jornada, string $estado, string $horario): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id,
            'id_equipo_local' => $this->local->id,
            'id_equipo_visitante' => $this->visitante->id,
            'jornada' => $jornada,
            'estado' => $estado,
            'horario_estimado' => $horario,
            'goles_casa' => $estado === 'Jugado' ? 1 : null,
            'goles_fuera' => $estado === 'Jugado' ? 0 : null,
        ]);
    }

    /** Una liga de esta temporada con un usuario que es su admin. */
    private function ligaConAdmin(string $tipo): array
    {
        $usuario = User::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => $tipo]);
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);

        return [$liga, $usuario];
    }

    /** Un pronóstico suelto, lo justo para que la liga "tenga algo que cerrar" en esa jornada. */
    private function pronostico(Liga $liga, User $usuario, CalendarioPartido $partido): void
    {
        DB::table('pronosticos')->insert([
            'id_usuario' => $usuario->id,
            'id_liga' => $liga->id,
            'id_partido' => $partido->id,
            'resultado_1x2' => 'Local',
            'goles_local_predicho' => 1,
            'goles_visitante_predicho' => 0,
            'enviado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pasos(array $estado, int $idLiga): array
    {
        $liga = collect($estado['ligas'])->firstWhere('id', $idLiga);

        return collect($liga['pasos'])->pluck('estado', 'clave')->all();
    }

    public function test_la_proxima_jornada_es_la_del_primer_partido_pendiente(): void
    {
        $this->partido(7, 'Jugado', '2026-09-19 18:30:00');
        $this->partido(8, 'Programado', '2026-10-09 21:00:00');
        $this->partido(8, 'Programado', '2026-10-10 14:00:00');
        $this->partido(9, 'Programado', '2026-10-18 00:00:00');

        $this->assertSame(8, CalendarioPartido::proximaJornadaPorJugar($this->temporada->id));
    }

    public function test_un_aplazado_lejos_de_su_jornada_no_hace_retroceder_la_proxima_jornada(): void
    {
        // Jornada 6: jugada a finales de septiembre, salvo un partido aplazado al 21 de octubre.
        $this->partido(6, 'Jugado', '2026-09-25 21:00:00');
        $this->partido(6, 'Jugado', '2026-09-26 18:30:00');
        $this->partido(6, 'Jugado', '2026-09-27 16:15:00');
        $aplazado = $this->partido(6, 'Programado', '2026-10-21 20:00:00');
        // La 9 ya terminó; lo siguiente en el calendario es el aplazado y, después, la 10.
        $this->partido(9, 'Jugado', '2026-10-18 21:00:00');
        $this->partido(10, 'Programado', '2026-10-24 16:15:00');
        $this->partido(10, 'Programado', '2026-10-25 21:00:00');

        $this->assertTrue($aplazado->estaDescolgadoDeSuJornada());
        $this->assertSame(10, CalendarioPartido::proximaJornadaPorJugar($this->temporada->id));
    }

    public function test_si_solo_queda_un_aplazado_por_jugar_esa_es_la_proxima_jornada(): void
    {
        $this->partido(6, 'Jugado', '2026-09-25 21:00:00');
        $this->partido(6, 'Jugado', '2026-09-26 18:30:00');
        $this->partido(6, 'Programado', '2026-10-21 20:00:00');

        $this->assertSame(6, CalendarioPartido::proximaJornadaPorJugar($this->temporada->id));
    }

    public function test_sin_partidos_pendientes_no_hay_proxima_jornada(): void
    {
        $this->partido(6, 'Jugado', '2026-09-25 21:00:00');

        $this->assertNull(CalendarioPartido::proximaJornadaPorJugar($this->temporada->id));
    }

    public function test_las_cartas_empiezan_en_la_primera_jornada_que_arranca_despues_de_repartirlas(): void
    {
        // Caso real: las primeras cartas se dieron el 3 de octubre. La 6 (con su aplazado
        // al 21) y la 7 ya habían empezado; la primera jornada con cartas es la 8.
        $this->partido(6, 'Jugado', '2026-09-25 21:00:00');
        $this->partido(6, 'Jugado', '2026-09-26 18:30:00');
        $this->partido(6, 'Jugado', '2026-09-27 16:15:00');
        $this->partido(6, 'Programado', '2026-10-21 20:00:00');
        $this->partido(7, 'Jugado', '2026-10-02 21:00:00');
        $this->partido(8, 'Programado', '2026-10-09 21:00:00');
        $this->partido(8, 'Programado', '2026-10-10 14:00:00');
        $this->partido(9, 'Programado', '2026-10-18 00:00:00');

        $servicio = app(CierreJornadaService::class);
        $primerReparto = \Illuminate\Support\Carbon::parse('2026-10-03 13:00:00');

        $this->assertSame(8, $servicio->primeraJornadaQueEmpiezaDespuesDe($this->temporada->id, $primerReparto));
        // Y una liga con cartas a la que aún no se le ha dado ninguna no tiene jornada de inicio.
        [$liga] = $this->ligaConAdmin('ConExtras');
        $this->assertNull($servicio->primeraJornadaConCartas($liga));
    }

    public function test_con_un_partido_sin_jugar_no_se_puede_cerrar_y_lo_dice(): void
    {
        [$liga, $usuario] = $this->ligaConAdmin('ConExtras');
        $this->partido(6, 'Jugado', '2026-09-25 21:00:00');
        $this->partido(6, 'Programado', '2026-10-21 20:00:00');

        $estado = app(CierreJornadaService::class)->estado(6, $usuario);

        $this->assertSame(2, $estado['partidos']['total']);
        $this->assertSame(1, $estado['partidos']['jugados']);
        $this->assertCount(1, $estado['partidos']['sin_jugar']);
        $this->assertSame(
            ['cerrar' => 'bloqueado', 'goleadores' => 'bloqueado', 'repartir' => 'bloqueado'],
            $this->pasos($estado, $liga->id)
        );
    }

    public function test_los_pasos_avanzan_en_orden_al_cerrar_y_calcular_goleadores(): void
    {
        [$liga, $usuario] = $this->ligaConAdmin('ConExtras');
        $this->partido(7, 'Jugado', '2026-09-19 18:30:00');
        $servicio = app(CierreJornadaService::class);

        // Todos jugados, sin cerrar: toca cerrar.
        $this->assertSame(
            ['cerrar' => 'listo', 'goleadores' => 'bloqueado', 'repartir' => 'bloqueado'],
            $this->pasos($servicio->estado(7, $usuario), $liga->id)
        );

        // Cerrada, pero al partido le faltan los datos de LaLiga: goleadores avisa,
        // y repartir avisa de que el Top 3 saldría sin los puntos de goleadores.
        $cierre = CierreJornada::create(['id_liga' => $liga->id, 'jornada' => 7, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $usuario->id]);
        $estado = $servicio->estado(7, $usuario);

        $this->assertCount(1, $estado['partidos']['sin_datos']);
        $this->assertSame(
            ['cerrar' => 'hecho', 'goleadores' => 'aviso', 'repartir' => 'aviso'],
            $this->pasos($estado, $liga->id)
        );

        // Goleadores calculados: ya solo queda repartir.
        $cierre->update(['goleadores_calculados_en' => now()]);

        $this->assertSame(
            ['cerrar' => 'hecho', 'goleadores' => 'hecho', 'repartir' => 'listo'],
            $this->pasos($servicio->estado(7, $usuario), $liga->id)
        );
    }

    public function test_una_liga_sin_cartas_no_tiene_paso_de_reparto(): void
    {
        [$liga, $usuario] = $this->ligaConAdmin('Normal');
        $this->partido(7, 'Jugado', '2026-09-19 18:30:00');

        $pasos = $this->pasos(app(CierreJornadaService::class)->estado(7, $usuario), $liga->id);

        $this->assertSame(['cerrar', 'goleadores'], array_keys($pasos));
    }

    public function test_las_jornadas_empezadas_sin_cerrar_salen_en_la_lista_de_pendientes(): void
    {
        [$liga, $usuario] = $this->ligaConAdmin('Normal');
        $this->pronostico($liga, $usuario, $this->partido(6, 'Jugado', '2026-09-25 21:00:00'));
        $this->partido(6, 'Programado', '2026-10-21 20:00:00');
        $this->pronostico($liga, $usuario, $this->partido(7, 'Jugado', '2026-10-03 18:30:00'));
        $this->partido(8, 'Programado', '2026-10-09 21:00:00');
        CierreJornada::create(['id_liga' => $liga->id, 'jornada' => 7, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $usuario->id]);

        $servicio = app(CierreJornadaService::class);
        $sinCerrar = collect($servicio->jornadasSinCerrar())->keyBy('jornada');

        $this->assertSame(7, $servicio->jornadaSugerida());
        // Solo la 6: la 7 está cerrada, la 8 no ha empezado y de la 1 a la 5 nadie pronosticó.
        $this->assertSame([6], $sinCerrar->keys()->all());
        $this->assertSame(1, $sinCerrar[6]['partidos_pendientes']);
    }

    public function test_una_liga_sin_pronosticos_no_ensucia_la_lista_de_pendientes(): void
    {
        [$liga, $usuario] = $this->ligaConAdmin('Normal');
        [$deprueba] = $this->ligaConAdmin('Normal');
        $this->pronostico($liga, $usuario, $this->partido(7, 'Jugado', '2026-10-03 18:30:00'));

        $sinCerrar = app(CierreJornadaService::class)->jornadasSinCerrar();

        $this->assertCount(1, $sinCerrar);
        $this->assertSame([$liga->nombre], $sinCerrar[0]['ligas']);
        $this->assertNotContains($deprueba->nombre, $sinCerrar[0]['ligas']);
    }

    public function test_partidos_con_una_jornada_fuera_de_rango_no_cuentan_como_jornada_a_cerrar(): void
    {
        // Caso real en la base de datos local: partidos "jugados" con jornada 47.
        $this->ligaConAdmin('Normal');
        $this->partido(7, 'Jugado', '2026-10-03 18:30:00');
        $this->partido(47, 'Jugado', '2026-10-04 18:30:00');

        $this->assertSame(7, app(CierreJornadaService::class)->jornadaSugerida());
    }
}
