<?php

namespace Tests\Feature;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\EjecucionTarea;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use App\Services\VigilanciaAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La vigilancia avisa al admin por la campana, una sola vez de cada cosa.
 */
class VigilanciaAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Liga $liga;
    private Temporada $temporada;
    private Equipo $local;
    private Equipo $visitante;
    private Jugador $jugador;
    private string $registros;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporada = Temporada::factory()->create();
        $this->local = Equipo::factory()->create();
        $this->visitante = Equipo::factory()->create();
        $this->jugador = Jugador::factory()->create();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['es_superadmin' => true])->save();
        User::factory()->create(); // un jugador normal: nunca debe recibir estos avisos

        $this->liga = Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => 'Normal', 'nombre' => 'BasiLiga']);

        // Carpeta de registros vacía y propia del test, para no leer los de verdad.
        $this->registros = sys_get_temp_dir().'/vigilancia-'.uniqid();
        mkdir($this->registros);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->registros.'/*') ?: []);
        rmdir($this->registros);

        parent::tearDown();
    }

    private function revisar(): array
    {
        return app(VigilanciaAdminService::class)->revisar($this->registros);
    }

    /** Un partido de la jornada 8; con o sin datos de LaLiga, con o sin pronóstico del admin. */
    private function partido(string $estado, $horario, bool $conDatos = true, bool $conPronostico = true): CalendarioPartido
    {
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $this->temporada->id, 'id_equipo_local' => $this->local->id, 'id_equipo_visitante' => $this->visitante->id,
            'jornada' => 8, 'estado' => $estado, 'horario_estimado' => $horario,
            'goles_casa' => $estado === 'Jugado' ? 1 : null, 'goles_fuera' => $estado === 'Jugado' ? 0 : null,
        ]);

        if ($conDatos) {
            AlineacionJugador::create(['id_partido' => $partido->id, 'id_equipo' => $this->local->id, 'id_jugador' => $this->jugador->id, 'dorsal' => 9, 'titular' => true]);
            DB::table('eventos_partido')->insert([
                'id_partido' => $partido->id, 'id_equipo' => $this->local->id, 'id_jugador' => $this->jugador->id,
                'minuto' => 10, 'tipo_evento' => 'gol', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($conPronostico) {
            DB::table('pronosticos')->insert([
                'id_usuario' => $this->admin->id, 'id_liga' => $this->liga->id, 'id_partido' => $partido->id,
                'resultado_1x2' => 'Local', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 0,
                'enviado_en' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $partido;
    }

    private function avisos(string $tipo): int
    {
        return $this->admin->notifications()->get()->filter(fn ($n) => ($n->data['tipo'] ?? null) === $tipo)->count();
    }

    public function test_avisa_una_sola_vez_de_que_la_jornada_se_puede_cerrar(): void
    {
        $this->partido('Jugado', now()->subHours(3));

        $this->assertSame(1, $this->revisar()['jornadas']);
        $this->assertSame(1, $this->avisos('admin_jornada_lista'));

        $aviso = $this->admin->notifications()->first();
        $this->assertTrue($aviso->data['importante']);
        $this->assertStringContainsString('jornada 8', $aviso->data['mensaje']);
        $this->assertStringContainsString('BasiLiga', $aviso->data['mensaje']);

        // Ni en la siguiente pasada, ni aunque el admin borre la notificación de su campana.
        $this->assertSame(0, $this->revisar()['jornadas']);
        $this->admin->notifications()->delete();
        $this->assertSame(0, $this->revisar()['jornadas']);
        $this->assertSame(0, $this->avisos('admin_jornada_lista'));

        // Y solo al admin: el jugador normal no recibe nada.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_no_avisa_si_queda_un_partido_por_jugar_si_ya_esta_cerrada_o_si_nadie_pronostico(): void
    {
        $this->partido('Jugado', now()->subHours(3));
        $pendiente = $this->partido('Programado', now()->addDay(), conDatos: false);
        $this->assertSame(0, $this->revisar()['jornadas'], 'Con un partido por jugar no se avisa');

        $pendiente->update(['estado' => 'Jugado', 'horario_estimado' => now()->subHours(2), 'goles_casa' => 0, 'goles_fuera' => 0]);
        AlineacionJugador::create(['id_partido' => $pendiente->id, 'id_equipo' => $this->local->id, 'id_jugador' => $this->jugador->id, 'dorsal' => 9, 'titular' => true]);
        DB::table('eventos_partido')->insert(['id_partido' => $pendiente->id, 'id_equipo' => $this->local->id, 'id_jugador' => $this->jugador->id, 'minuto' => 5, 'tipo_evento' => 'tarjeta_amarilla', 'created_at' => now(), 'updated_at' => now()]);

        CierreJornada::create(['id_liga' => $this->liga->id, 'jornada' => 8, 'cerrada' => true, 'cerrada_en' => now(), 'cerrada_por' => $this->admin->id]);
        $this->assertSame(0, $this->revisar()['jornadas'], 'Ya cerrada: no se avisa');

        // Otra liga de la misma temporada en la que nadie pronosticó: tampoco.
        Liga::factory()->create(['id_temporada' => $this->temporada->id, 'tipo' => 'Normal']);
        $this->assertSame(0, $this->revisar()['jornadas']);
    }

    public function test_si_faltan_datos_de_laliga_espera_y_pasado_un_rato_avisa_diciendolo(): void
    {
        $partido = $this->partido('Jugado', now()->subHours(2), conDatos: false);
        $this->assertSame(0, $this->revisar()['jornadas'], 'Recién terminado y sin datos: se espera');

        $partido->update(['horario_estimado' => now()->subHours(5)]);
        $this->assertSame(1, $this->revisar()['jornadas']);
        $this->assertStringContainsString('faltan datos de LaLiga', $this->admin->notifications()->first()->data['mensaje']);
    }

    public function test_no_avisa_de_jornadas_antiguas(): void
    {
        $this->partido('Jugado', now()->subDays(20));

        $this->assertSame(0, $this->revisar()['jornadas']);
    }

    public function test_avisa_de_una_tarea_programada_que_falla_solo_una_vez_al_dia(): void
    {
        $ejecucion = EjecucionTarea::create([
            'tarea' => 'sincronizar-partidos', 'origen' => 'programada', 'estado' => 'fallo',
            'salida' => 'Terminó con código de salida 1.', 'iniciada_en' => now(), 'terminada_en' => now(),
        ]);

        $this->assertSame(1, $this->revisar()['tareas']);
        $this->assertStringContainsString('Sincronizar partidos', $this->admin->notifications()->first()->data['mensaje']);

        // Sigue fallando dos minutos después: no se repite el aviso.
        $ejecucion->update(['terminada_en' => now()]);
        $this->assertSame(0, $this->revisar()['tareas']);
        $this->assertSame(1, $this->avisos('admin_tarea_fallida'));
    }

    public function test_avisa_de_un_error_nuevo_de_los_registros_y_no_lo_repite(): void
    {
        $ahora = now()->format('Y-m-d H:i:s');
        file_put_contents($this->registros.'/laravel-'.now()->format('Y-m-d').'.log', implode("\n", [
            "[{$ahora}] production.ERROR: Fallo al importar el partido 79",
            "[{$ahora}] production.ERROR: Fallo al importar el partido 80",
            "[{$ahora}] production.INFO: Todo bien",
            '',
        ]));

        // Los dos errores son el mismo fallo (solo cambia un número): un único aviso.
        $this->assertSame(1, $this->revisar()['errores']);
        $this->assertSame(0, $this->revisar()['errores']);

        $aviso = $this->admin->notifications()->first();
        $this->assertSame('admin_error_nuevo', $aviso->data['tipo']);
        $this->assertStringContainsString('2 veces', $aviso->data['mensaje']);
    }

    public function test_sin_nada_que_contar_no_avisa(): void
    {
        $this->assertSame(['jornadas' => 0, 'tareas' => 0, 'errores' => 0], $this->revisar());
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
