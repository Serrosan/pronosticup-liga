<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Equipo;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Notifications\CartasSinJugar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El aviso de cartas sin jugar respeta los límites (2 Jugadas, 1 Falta) y separa Jugadas y Faltas. */
class AvisoCartasSinJugarTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $usuario;
    private TipoCarta $chute;
    private TipoCarta $casa;
    private TipoCarta $expulsion;

    protected function setUp(): void
    {
        parent::setUp();
        $temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras', 'nombre' => 'BasiLiga']);
        $this->usuario = User::factory()->create(['liga_activa_id' => $this->liga->id]);
        $this->liga->usuarios()->attach($this->usuario->id, ['rol' => 'Miembro']);

        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 12, 'estado' => 'Programado', 'horario_estimado' => now()->addHours(5),
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        $jugadas = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
        $faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $tipo = fn ($cat, $nombre, $codigo, $rareza = 'Comun') => TipoCarta::create([
            'id_categoria' => $cat->id, 'rareza' => $rareza, 'nombre' => $nombre, 'descripcion' => $nombre, 'codigo_efecto' => $codigo, 'activa' => true,
        ]);
        $this->chute = $tipo($jugadas, 'Chute Extra', 'JUG-COM-CHUTE');
        $this->casa = $tipo($faltas, 'Todo queda en casa', 'FAL-COM-CASA');
        $this->expulsion = $tipo($faltas, 'Expulsión', 'FAL-LEG-EXPULSION', 'Legendaria');
    }

    private function carta(TipoCarta $tipo, string $estado = 'en_mano', array $extra = []): void
    {
        CartaUsuario::create(array_merge([
            'id_usuario' => $this->usuario->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => 11, 'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'manual', 'estado' => $estado,
        ], $estado === 'jugada' ? ['jugada_en' => now()] : [], $extra));
    }

    private function aviso(): ?string
    {
        $this->artisan('liga:avisar-pendientes')->assertSuccessful();

        return $this->usuario->notifications()->where('type', CartasSinJugar::class)->first()?->data['mensaje'];
    }

    public function test_si_ya_jugaste_las_2_jugadas_no_te_avisa_de_jugadas(): void
    {
        $this->carta($this->chute, 'jugada', ['jornada_efecto' => 12]);
        $this->carta($this->chute, 'jugada', ['jornada_efecto' => 12]);
        $this->carta($this->chute); // te quedan en la mano, pero ya no puedes jugarlas

        $this->assertNull($this->aviso());
    }

    public function test_te_dice_cuantas_jugadas_te_quedan_y_la_falta(): void
    {
        $this->carta($this->chute, 'jugada', ['jornada_efecto' => 12]);
        $this->carta($this->chute);
        $this->carta($this->chute);
        $this->carta($this->casa);

        $mensaje = $this->aviso();

        $this->assertStringContainsString('Te queda 1 Jugada por jugar (tienes 2 en la mano)', $mensaje);
        $this->assertStringContainsString('Y tu Falta de esta semana: afectará a la jornada 13', $mensaje);
    }

    public function test_si_ya_jugaste_la_falta_solo_habla_de_jugadas(): void
    {
        $this->carta($this->casa, 'jugada', ['jornada_efecto' => 13, 'id_usuario_objetivo' => User::factory()->create()->id]);
        $this->carta($this->casa);
        $this->carta($this->chute);

        $mensaje = $this->aviso();

        $this->assertStringContainsString('Te queda 1 Jugada', $mensaje);
        $this->assertStringNotContainsString('Falta', $mensaje);
    }

    public function test_solo_faltas_en_la_mano(): void
    {
        $this->carta($this->casa);

        $this->assertStringContainsString('Te queda tu Falta de esta semana por jugar', $this->aviso());
    }

    public function test_expulsado_no_te_anima_a_jugar(): void
    {
        $this->carta($this->chute);
        $this->carta($this->casa);
        // Expulsión legendaria contra ti en la jornada 12: ni Jugadas ni Faltas
        CartaUsuario::create([
            'id_usuario' => User::factory()->create()->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $this->expulsion->id,
            'jornada_obtenida' => 11, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $this->usuario->id, 'jornada_efecto' => 12, 'jugada_en' => now(),
        ]);

        $this->assertNull($this->aviso());
    }
}
