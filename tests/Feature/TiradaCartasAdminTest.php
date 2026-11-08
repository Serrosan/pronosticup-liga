<?php

namespace Tests\Feature;

use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Notifications\CartaRecibidaManual;
use App\Notifications\CartaRetiradaCompensada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TiradaCartasAdminTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $admin;
    private User $jugador;
    private TipoCarta $chute;
    private TipoCarta $silbato;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'ConExtras']);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['es_superadmin' => true])->save();
        $this->jugador = User::factory()->create();
        $this->liga->usuarios()->attach($this->jugador->id, ['rol' => 'Miembro']);

        $jugadas = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
        $faltas = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $this->chute = TipoCarta::create(['id_categoria' => $jugadas->id, 'rareza' => 'Comun', 'nombre' => 'Chute Extra', 'descripcion' => 'x', 'codigo_efecto' => 'JUG-COM-CHUTE', 'activa' => true]);
        TipoCarta::create(['id_categoria' => $faltas->id, 'rareza' => 'Comun', 'nombre' => 'Todo queda en casa', 'descripcion' => 'x', 'codigo_efecto' => 'FAL-COM-CASA', 'activa' => true]);
        $this->silbato = TipoCarta::create(['id_categoria' => $faltas->id, 'rareza' => 'Comun', 'nombre' => 'Silbato', 'descripcion' => 'x', 'codigo_efecto' => 'FAL-COM-SILBATO', 'activa' => true]);
    }

    private function post(string $url, array $datos)
    {
        return $this->actingAs($this->admin)->postJson($url, $datos);
    }

    public function test_dar_una_tirada_a_un_jugador_crea_un_sobre_y_le_avisa(): void
    {
        $this->post('/api/v1/admin/cartas-usuario/tirada', ['id_liga' => $this->liga->id, 'id_usuario' => $this->jugador->id, 'rareza' => 'Comun'])
            ->assertOk();

        $carta = CartaUsuario::where('id_usuario', $this->jugador->id)->first();
        $this->assertSame('en_mano', $carta->estado);
        $this->assertNull($carta->revelada_en, 'Llega como sobre sin abrir');
        Notification::assertSentTo($this->jugador, CartaRecibidaManual::class);
    }

    public function test_la_tirada_respeta_la_categoria_elegida(): void
    {
        $this->post('/api/v1/admin/cartas-usuario/tirada', ['id_liga' => $this->liga->id, 'id_usuario' => $this->jugador->id, 'id_categoria' => $this->chute->id_categoria])
            ->assertOk();

        $this->assertSame($this->chute->id, CartaUsuario::where('id_usuario', $this->jugador->id)->value('id_tipo_carta'));
    }

    public function test_no_se_da_tirada_a_quien_no_es_de_la_liga(): void
    {
        $this->post('/api/v1/admin/cartas-usuario/tirada', ['id_liga' => $this->liga->id, 'id_usuario' => User::factory()->create()->id])
            ->assertStatus(422);
    }

    public function test_retirar_una_carta_compensando_da_un_sobre_por_cada_una(): void
    {
        foreach ([1, 2] as $_) {
            CartaUsuario::create(['id_usuario' => $this->jugador->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $this->silbato->id,
                'jornada_obtenida' => 10, 'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'manual', 'estado' => 'en_mano']);
        }

        $this->actingAs($this->admin)->getJson("/api/v1/admin/tipos-carta/{$this->silbato->id}/retirada")
            ->assertOk()->assertJsonPath('data.en_mano', 2);

        $this->post("/api/v1/admin/tipos-carta/{$this->silbato->id}/retirar", ['compensar' => true])->assertOk();

        $this->assertFalse((bool) $this->silbato->fresh()->activa);
        $this->assertSame(2, CartaUsuario::where('id_tipo_carta', $this->silbato->id)->where('estado', 'retirada_por_catalogo')->count());
        $nuevas = CartaUsuario::where('id_usuario', $this->jugador->id)->where('estado', 'en_mano')->get();
        $this->assertCount(2, $nuevas);
        $this->assertNotContains($this->silbato->id, $nuevas->pluck('id_tipo_carta'), 'La retirada no puede volver a salir');
        Notification::assertSentTo($this->jugador, CartaRetiradaCompensada::class, fn ($n) => $n->sobres === 2);
    }

    public function test_retirar_sin_compensar_solo_la_quita(): void
    {
        CartaUsuario::create(['id_usuario' => $this->jugador->id, 'id_liga' => $this->liga->id, 'id_tipo_carta' => $this->silbato->id,
            'jornada_obtenida' => 10, 'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'manual', 'estado' => 'en_mano']);

        $this->post("/api/v1/admin/tipos-carta/{$this->silbato->id}/retirar", ['compensar' => false])->assertOk();

        $this->assertSame(0, CartaUsuario::where('id_usuario', $this->jugador->id)->where('estado', 'en_mano')->count());
        Notification::assertNothingSent();
    }

    public function test_un_jugador_no_puede_usarlo(): void
    {
        $this->actingAs($this->jugador)->postJson('/api/v1/admin/cartas-usuario/tirada', ['id_liga' => $this->liga->id, 'id_usuario' => $this->jugador->id])
            ->assertForbidden();
    }
}
