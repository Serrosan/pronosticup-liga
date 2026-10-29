<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Con varias Faltas de requisito a la vez, el aviso las enseña todas y dice cuál queda tapada. */
class AvisoVariasFaltasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_estado_trae_todas_las_condiciones_y_las_faltas_tapadas(): void
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras']);
        $victima = User::factory()->create(['name' => 'Víctima']);
        $ana = User::factory()->create(['name' => 'Ana', 'nombre_visible' => 'Ana']);
        $luis = User::factory()->create(['name' => 'Luis', 'nombre_visible' => 'Luis']);
        foreach ([$victima, $ana, $luis] as $u) {
            $liga->usuarios()->attach($u->id, ['rol' => 'Miembro']);
        }

        $faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $tipo = fn ($codigo, $nombre, $rareza) => TipoCarta::create([
            'id_categoria' => $faltas->id, 'rareza' => $rareza, 'nombre' => $nombre,
            'descripcion' => $nombre, 'codigo_efecto' => $codigo, 'activa' => true,
        ]);
        $empates3 = $tipo('FAL-RAR-EMPATE', 'Resultado Gafas', 'Rara');
        $visita3 = $tipo('FAL-PCOM-VISITA', 'Visita Obligada', 'PocoComun');
        $visita2 = $tipo('FAL-COM-VISITA', 'Visita Obligada', 'Comun');

        foreach ([[$ana, $empates3], [$ana, $visita3], [$luis, $visita2]] as [$atacante, $t]) {
            CartaUsuario::create([
                'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $t->id,
                'jornada_obtenida' => 11, 'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'manual',
                'estado' => 'jugada', 'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 12, 'jugada_en' => now(),
            ]);
        }

        $partidos = CalendarioPartido::factory()->count(8)->create([
            'id_temporada' => $temporada->id, 'jornada' => 12, 'estado' => 'Programado', 'horario_estimado' => now()->addDays(2),
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        // Ya lleva 2 empates; nada de visitantes.
        foreach ($partidos->take(2) as $p) {
            Pronostico::create([
                'id_usuario' => $victima->id, 'id_liga' => $liga->id, 'id_partido' => $p->id,
                'resultado_1x2' => 'Empate', 'goles_local_predicho' => 1, 'goles_visitante_predicho' => 1,
            ]);
        }

        $estado = app(MotorFaltas::class)->estadoRequisitoPronostico($liga->id, $victima->id, 12, $partidos->pluck('id'));
        $porTipo = collect($estado['requisitos'])->keyBy('tipo');

        $this->assertSame(['cantidad' => 3, 'cumplidos' => 2], ['cantidad' => $porTipo['Empate']['cantidad'], 'cumplidos' => $porTipo['Empate']['cumplidos']]);
        $this->assertSame(3, $porTipo['Visitante']['cantidad'], 'Manda la más exigente');
        $this->assertSame(4, $estado['faltan'], '1 empate + 3 visitantes');
        $this->assertSame(6, $estado['partidos_sin_pronosticar']);
        $this->assertTrue($estado['cumplible']);

        $visitantes = $porTipo['Visitante']['faltas'];
        $this->assertCount(2, $visitantes);
        $this->assertSame([3, true, 'Ana'], [$visitantes[0]['cantidad'], $visitantes[0]['manda'], $visitantes[0]['atacante']]);
        $this->assertSame([2, false, 'Luis'], [$visitantes[1]['cantidad'], $visitantes[1]['manda'], $visitantes[1]['atacante']]);
    }
}
