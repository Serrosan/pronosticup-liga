<?php

namespace Tests\Feature;

use App\Jobs\Admin\EjecutarTareaAdminJob;
use App\Models\CalendarioPartido;
use App\Models\EjecucionTarea;
use App\Models\Equipo;
use App\Models\Temporada;
use App\Services\TareasAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registro de ejecuciones de /admin/tareas. Nada de aquí sale a internet:
 * los casos de reimportación se quedan en los que no llegan a descargar.
 */
class TareasAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pasada_de_una_tarea_programada_se_apunta_en_una_sola_fila_que_se_actualiza(): void
    {
        TareasAdmin::registrarProgramada("'/usr/bin/php' 'artisan' liga:sincronizar-partidos", true, 1.5);
        TareasAdmin::registrarProgramada("'/usr/bin/php' 'artisan' liga:sincronizar-partidos", false, null, 'Terminó con código de salida 1.');

        $this->assertSame(1, EjecucionTarea::count());
        $this->assertDatabaseHas('ejecuciones_tarea', [
            'tarea' => 'sincronizar-partidos',
            'origen' => 'programada',
            'estado' => 'fallo',
            'salida' => 'Terminó con código de salida 1.',
        ]);
    }

    public function test_una_tarea_que_no_esta_en_el_catalogo_no_se_apunta(): void
    {
        TareasAdmin::registrarProgramada("'/usr/bin/php' 'artisan' queue:work --stop-when-empty --max-time=50", true, 0.1);
        // Se parece a la de sincronizar partidos, pero es otro comando.
        TareasAdmin::registrarProgramada("'/usr/bin/php' 'artisan' liga:sincronizar-partido-laliga 7", true, 0.1);

        $this->assertSame(0, EjecucionTarea::count());
    }

    public function test_el_catalogo_resuelve_tareas_programadas_y_herramientas_y_nada_mas(): void
    {
        $this->assertSame('liga:sincronizar-partidos', TareasAdmin::comando('sincronizar-partidos'));
        $this->assertSame('liga:completar-ids-api', TareasAdmin::comando('completar-ids-api'));
        $this->assertSame('liga:comparar-plantillas', TareasAdmin::comando('comparar-plantillas'));
        $this->assertNull(TareasAdmin::comando('migrate:fresh'));
        $this->assertNull(TareasAdmin::comando(TareasAdmin::REIMPORTAR_JORNADA));
    }

    public function test_la_salida_larga_se_recorta_quedandose_con_el_final(): void
    {
        $recortada = TareasAdmin::recortar(str_repeat('a', 9000).'FINAL');

        $this->assertLessThan(6100, strlen($recortada));
        $this->assertStringEndsWith('FINAL', $recortada);
    }

    public function test_un_lanzamiento_manual_de_una_tarea_desconocida_queda_como_fallido(): void
    {
        $ejecucion = EjecucionTarea::create(['tarea' => 'borrar-todo', 'origen' => 'manual', 'estado' => 'en_cola']);

        (new EjecutarTareaAdminJob($ejecucion->id))->handle();

        $ejecucion->refresh();
        $this->assertSame('fallo', $ejecucion->estado);
        $this->assertSame('Tarea desconocida.', $ejecucion->salida);
        $this->assertNotNull($ejecucion->terminada_en);
    }

    public function test_reimportar_una_jornada_sin_partidos_queda_como_fallida_sin_descargar_nada(): void
    {
        Temporada::factory()->create();
        $ejecucion = EjecucionTarea::create([
            'tarea' => TareasAdmin::REIMPORTAR_JORNADA, 'origen' => 'manual', 'estado' => 'en_cola', 'parametros' => ['jornada' => 30],
        ]);

        (new EjecutarTareaAdminJob($ejecucion->id))->handle();

        $ejecucion->refresh();
        $this->assertSame('fallo', $ejecucion->estado);
        $this->assertSame('No hay partidos que reimportar.', $ejecucion->salida);
    }

    public function test_reimportar_se_salta_los_partidos_que_aun_no_han_terminado(): void
    {
        $temporada = Temporada::factory()->create();
        $local = Equipo::factory()->create(['nombre' => 'Sevilla FC', 'nombre_corto' => 'Sevilla']);
        $visitante = Equipo::factory()->create(['nombre' => 'Rayo Vallecano', 'nombre_corto' => 'Rayo']);
        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'id_equipo_local' => $local->id,
            'id_equipo_visitante' => $visitante->id,
            'jornada' => 30,
            'horario_estimado' => now()->addDays(3),
        ]);
        $ejecucion = EjecucionTarea::create([
            'tarea' => TareasAdmin::REIMPORTAR_JORNADA, 'origen' => 'manual', 'estado' => 'en_cola', 'parametros' => ['jornada' => 30],
        ]);

        (new EjecutarTareaAdminJob($ejecucion->id))->handle();

        $ejecucion->refresh();
        $this->assertSame('fallo', $ejecucion->estado);
        $this->assertStringContainsString('Sevilla - Rayo (J30): aún no ha terminado', $ejecucion->salida);
        $this->assertStringContainsString('0 importado(s)', $ejecucion->salida);
    }

    public function test_un_lanzamiento_que_ya_no_esta_en_cola_no_se_ejecuta_dos_veces(): void
    {
        $ejecucion = EjecucionTarea::create(['tarea' => 'borrar-todo', 'origen' => 'manual', 'estado' => 'ok', 'salida' => 'ya hecho']);

        (new EjecutarTareaAdminJob($ejecucion->id))->handle();

        $this->assertSame('ya hecho', $ejecucion->refresh()->salida);
    }
}
