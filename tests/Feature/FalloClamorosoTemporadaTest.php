<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fallo clamoroso: el TOP 3 es de la temporada de la liga (no la suma de todas)
 * y, con empate en el 3.º puesto, entran todos los empatados. Las estadísticas
 * de goleadores también cuentan solo esa temporada.
 */
class FalloClamorosoTemporadaTest extends TestCase
{
    use RefreshDatabase;

    private Temporada $pasada;
    private Temporada $actual;
    private Equipo $equipo;
    private Equipo $rival;
    /** @var array<string,Jugador> */
    private array $j = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasada = Temporada::factory()->create(['fecha_inicio' => '2025-08-15']);
        $this->actual = Temporada::factory()->create(['fecha_inicio' => '2026-08-15']);
        $this->equipo = Equipo::factory()->create();
        $this->rival = Equipo::factory()->create();

        foreach (['viejo', 'a', 'b', 'c', 'd', 'e'] as $nombre) {
            $this->j[$nombre] = Jugador::factory()->create(['nombre_camiseta' => strtoupper($nombre)]);
        }

        // La temporada pasada, "viejo" metió 10: no debe contar en la actual.
        $this->goles($this->pasada, 'viejo', 10);
        // Esta temporada: A 5, B 4, C 3, D 3 (empata con C en el 3.º), E 1.
        foreach (['a' => 5, 'b' => 4, 'c' => 3, 'd' => 3, 'e' => 1] as $nombre => $total) {
            $this->goles($this->actual, $nombre, $total);
        }
    }

    private function goles(Temporada $temporada, string $jugador, int $total): void
    {
        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 1, 'estado' => 'Jugado',
            'id_equipo_local' => $this->equipo->id, 'id_equipo_visitante' => $this->rival->id,
            'horario_estimado' => now()->subDays(30), 'goles_casa' => $total, 'goles_fuera' => 0,
        ]);

        foreach (range(1, $total) as $minuto) {
            DB::table('eventos_partido')->insert([
                'id_partido' => $partido->id, 'id_jugador' => $this->j[$jugador]->id, 'id_equipo' => $this->equipo->id,
                'minuto' => $minuto, 'tipo_evento' => 'gol', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function ids(string ...$nombres): array
    {
        return collect($nombres)->map(fn ($n) => $this->j[$n]->id)->sort()->values()->all();
    }

    public function test_top_3_de_la_temporada_con_empatados_en_el_tercer_puesto(): void
    {
        $motor = app(MotorFaltas::class);

        $actual = collect($motor->topGoleadoresRealesIds($this->actual->id))->sort()->values()->all();
        $this->assertSame($this->ids('a', 'b', 'c', 'd'), $actual, 'C y D empatan en el 3.º: entran los dos; el de la temporada pasada no');

        $this->assertSame([$this->j['viejo']->id], $motor->topGoleadoresRealesIds($this->pasada->id));
    }

    public function test_con_la_falta_activa_se_bloquea_a_los_del_top_de_esta_temporada(): void
    {
        $liga = Liga::factory()->create(['id_temporada' => $this->actual->id, 'tipo' => 'ConExtras']);
        [$atacante, $victima] = [User::factory()->create(), User::factory()->create()];
        $victima->forceFill(['liga_activa_id' => $liga->id])->save();
        $liga->usuarios()->attach($victima->id, ['rol' => 'Admin', 'se_unio_en' => now()]);

        $categoria = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $fallo = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'PocoComun', 'nombre' => 'Fallo clamoroso',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-PCOM-FALLO', 'activa' => true,
        ]);
        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $fallo->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        Sanctum::actingAs($victima);
        $bloqueados = collect(array_keys($this->getJson('/api/v1/jornadas/6/goleadores-bloqueados')->assertOk()->json('data')))
            ->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($this->ids('a', 'b', 'c', 'd'), $bloqueados);
    }

    public function test_las_estadisticas_de_goleadores_son_de_la_temporada_de_tu_liga(): void
    {
        $liga = Liga::factory()->create(['id_temporada' => $this->actual->id, 'tipo' => 'ConExtras']);
        $usuario = User::factory()->create();
        $usuario->forceFill(['liga_activa_id' => $liga->id])->save();
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Admin', 'se_unio_en' => now()]);

        Sanctum::actingAs($usuario);
        $filas = collect($this->getJson('/api/v1/estadisticas-jugadores/goleadores')->assertOk()->json('data'));

        $this->assertSame($this->j['a']->id, $filas->first()['id']);
        $this->assertSame(5, (int) $filas->first()['goles']);
        $this->assertNull($filas->firstWhere('id', $this->j['viejo']->id), 'Los goles de la temporada pasada no salen');
    }
}
