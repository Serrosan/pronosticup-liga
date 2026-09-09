<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Temporada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioPartidoTest extends TestCase
{
    use RefreshDatabase;

    private function partido(int $idTemporada, int $jornada, string $estado, string $horario): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $idTemporada,
            'jornada' => $jornada,
            'estado' => $estado,
            'horario_estimado' => $horario,
            'id_equipo_local' => Equipo::factory(),
            'id_equipo_visitante' => Equipo::factory(),
        ]);
    }

    public function test_jornada_sin_ningun_partido_no_esta_bloqueada(): void
    {
        $temporada = Temporada::factory()->create();

        $this->assertFalse(CalendarioPartido::jornadaBloqueada($temporada->id, 1));
    }

    public function test_jornada_con_todos_los_partidos_programados_no_esta_bloqueada(): void
    {
        $temporada = Temporada::factory()->create();

        $this->partido($temporada->id, 1, 'Programado', '2026-09-20 19:00:00');
        $this->partido($temporada->id, 1, 'Programado', '2026-09-21 19:00:00');
        $this->partido($temporada->id, 1, 'Programado', '2026-09-21 21:00:00');

        $this->assertFalse(CalendarioPartido::jornadaBloqueada($temporada->id, 1));
    }

    public function test_jornada_bloqueada_si_un_partido_del_grueso_principal_ya_empezo(): void
    {
        $temporada = Temporada::factory()->create();

        $this->partido($temporada->id, 1, 'Jugado', '2026-09-20 19:00:00');
        $this->partido($temporada->id, 1, 'Programado', '2026-09-21 19:00:00');
        $this->partido($temporada->id, 1, 'Programado', '2026-09-21 21:00:00');

        $this->assertTrue(CalendarioPartido::jornadaBloqueada($temporada->id, 1));
    }

    public function test_partido_adelantado_dos_semanas_antes_no_bloquea_el_resto_de_la_jornada(): void
    {
        $temporada = Temporada::factory()->create();

        // El caso real que motivó este arreglo: un partido se jugó 2 semanas antes que el resto
        $this->partido($temporada->id, 6, 'Jugado', '2026-09-03 19:00:00');

        // El grueso principal de la jornada sigue a semanas vista, todavía sin jugar
        $this->partido($temporada->id, 6, 'Programado', '2026-09-15 19:00:00');
        $this->partido($temporada->id, 6, 'Programado', '2026-09-16 20:00:00');
        $this->partido($temporada->id, 6, 'Programado', '2026-09-17 21:30:00');

        $this->assertFalse(CalendarioPartido::jornadaBloqueada($temporada->id, 6));
    }

    public function test_partido_aplazado_dentro_del_grueso_principal_si_bloquea(): void
    {
        $temporada = Temporada::factory()->create();

        $this->partido($temporada->id, 3, 'Programado', '2026-09-20 19:00:00');
        $this->partido($temporada->id, 3, 'Aplazado', '2026-09-21 19:00:00');
        $this->partido($temporada->id, 3, 'Programado', '2026-09-21 21:00:00');

        $this->assertTrue(CalendarioPartido::jornadaBloqueada($temporada->id, 3));
    }

    public function test_jornada_totalmente_jugada_esta_bloqueada(): void
    {
        $temporada = Temporada::factory()->create();

        $this->partido($temporada->id, 1, 'Jugado', '2026-09-20 19:00:00');
        $this->partido($temporada->id, 1, 'Jugado', '2026-09-21 19:00:00');

        $this->assertTrue(CalendarioPartido::jornadaBloqueada($temporada->id, 1));
    }
}