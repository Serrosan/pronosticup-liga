<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Temporada;
use App\Models\User;
use App\Notifications\AvisoAdmin;
use App\Services\VigilanciaAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PartidoAtascadoTest extends TestCase
{
    use RefreshDatabase;

    private function partido(int $idTemporada, string $estado, $horario): CalendarioPartido
    {
        return CalendarioPartido::factory()->create([
            'id_temporada' => $idTemporada, 'jornada' => 10, 'estado' => $estado,
            'horario_estimado' => $horario,
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);
    }

    public function test_avisa_una_vez_del_partido_que_lleva_horas_sin_terminar(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->forceFill(['es_superadmin' => true])->save();
        $temporada = Temporada::factory()->create();

        $atascado = $this->partido($temporada->id, 'Programado', now()->subHours(5));
        $this->partido($temporada->id, 'Programado', now()->subHour());          // aún dentro de lo normal
        $this->partido($temporada->id, 'Aplazado', now()->subHours(5));         // aplazado: no es un atasco
        $this->partido($temporada->id, 'Jugado', now()->subHours(5));           // ya terminado
        $this->partido($temporada->id, 'Programado', now()->subDays(20));       // demasiado antiguo

        $vigilancia = app(VigilanciaAdminService::class);

        $this->assertSame(1, $vigilancia->partidosAtascados());
        Notification::assertSentTo($admin, AvisoAdmin::class, fn ($n) => $n->tipo === 'admin_partido_atascado'
            && str_contains($n->mensaje, 'jornada 10'));

        // Segunda pasada: del mismo partido no se vuelve a avisar.
        $this->assertSame(0, $vigilancia->partidosAtascados());
        Notification::assertSentToTimes($admin, AvisoAdmin::class, 1);

        $this->assertNotNull($atascado);
    }
}
