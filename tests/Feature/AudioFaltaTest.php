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
use App\Notifications\FaltaJugadaContraTi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Al jugar una Falta se puede mandar, además del mensaje, un audio corto. */
class AudioFaltaTest extends TestCase
{
    use RefreshDatabase;

    private User $atacante;
    private User $rival;
    private CartaUsuario $carta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras']);

        $this->atacante = User::factory()->create(['liga_activa_id' => $liga->id]);
        $this->rival = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($this->atacante->id, ['rol' => 'Admin']);
        $liga->usuarios()->attach($this->rival->id, ['rol' => 'Miembro']);

        // Una jornada por jugar, para que haya dónde caer la Falta.
        CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id, 'jornada' => 12, 'estado' => 'Programado',
            'horario_estimado' => now()->addDays(3),
            'id_equipo_local' => Equipo::factory(), 'id_equipo_visitante' => Equipo::factory(),
        ]);

        $faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $tipo = TipoCarta::create([
            'id_categoria' => $faltas->id, 'rareza' => 'Comun', 'nombre' => 'Todo queda en casa',
            'descripcion' => 'Victorias locales', 'codigo_efecto' => 'FAL-COM-CASA', 'activa' => true,
        ]);

        $this->carta = CartaUsuario::create([
            'id_usuario' => $this->atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => 11, 'obtenida_en' => now(), 'revelada_en' => now(),
            'origen' => 'manual', 'estado' => 'en_mano',
        ]);
    }

    private function cabeceras(): array
    {
        return ['Origin' => 'http://localhost', 'Referer' => 'http://localhost', 'Accept' => 'application/json'];
    }

    public function test_la_falta_se_juega_con_su_audio_y_la_victima_lo_puede_escuchar(): void
    {
        $this->actingAs($this->atacante)->withHeaders($this->cabeceras())
            ->post("/api/v1/mis-cartas/{$this->carta->id}/jugar-falta", [
                'id_usuario_objetivo' => $this->rival->id,
                'mensaje' => 'Toma ya',
                'audio' => UploadedFile::fake()->create('burla.m4a', 40, 'audio/mp4'),
            ])
            ->assertOk();

        $carta = $this->carta->fresh();
        $this->assertSame('jugada', $carta->estado);
        $this->assertNotNull($carta->audio_falta);
        Storage::disk('public')->assertExists($carta->audio_falta);

        Notification::assertSentTo($this->rival, FaltaJugadaContraTi::class, fn ($n) => $n->conAudio === true);

        $amenazas = $this->actingAs($this->rival)->withHeaders($this->cabeceras())->getJson('/api/v1/mis-cartas')->json('data.faltas_recibidas');
        $this->assertNotEmpty($amenazas[0]['audio_url']);
    }

    public function test_sin_audio_todo_sigue_como_antes(): void
    {
        $this->actingAs($this->atacante)->withHeaders($this->cabeceras())
            ->postJson("/api/v1/mis-cartas/{$this->carta->id}/jugar-falta", ['id_usuario_objetivo' => $this->rival->id])
            ->assertOk();

        $this->assertNull($this->carta->fresh()->audio_falta);
        Notification::assertSentTo($this->rival, FaltaJugadaContraTi::class, fn ($n) => $n->conAudio === false);
    }

    public function test_un_archivo_que_no_es_audio_no_se_acepta_y_la_carta_sigue_en_la_mano(): void
    {
        $this->actingAs($this->atacante)->withHeaders($this->cabeceras())
            ->post("/api/v1/mis-cartas/{$this->carta->id}/jugar-falta", [
                'id_usuario_objetivo' => $this->rival->id,
                'audio' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422);

        $this->assertSame('en_mano', $this->carta->fresh()->estado);
    }
}
