<?php

namespace Tests\Feature;

use App\Models\CanjeCodigo;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\CodigoCanje;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanjeCodigoTest extends TestCase
{
    use RefreshDatabase;

    private Liga $liga;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $temporada = Temporada::factory()->create();
        $this->liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras']);
        $this->usuario = User::factory()->create(['liga_activa_id' => $this->liga->id]);

        CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
    }

    public function test_canjear_una_carta_especifica_da_exactamente_esa_carta(): void
    {
        $categoria = CategoriaCarta::first();
        $tipo = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Legendaria', 'nombre' => 'Crack',
            'descripcion' => 'test', 'codigo_efecto' => 'JUG-LEG-CRACK', 'activa' => true,
        ]);

        CodigoCanje::create([
            'codigo' => 'REGALOESPECIAL', 'nombre' => 'test', 'tipo_premio' => 'carta_especifica',
            'id_tipo_carta' => $tipo->id, 'activo' => true,
        ]);

        $respuesta = $this->actingAs($this->usuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'regaloespecial']);

        $respuesta->assertOk();
        $this->assertSame('Crack', $respuesta->json('data.tipo_carta.nombre'));
        $this->assertDatabaseHas('cartas_usuario', [
            'id_usuario' => $this->usuario->id, 'id_tipo_carta' => $tipo->id, 'estado' => 'en_mano',
        ]);
        $this->assertSame(1, CanjeCodigo::where('id_usuario', $this->usuario->id)->count());
    }

    public function test_no_se_puede_canjear_el_mismo_codigo_dos_veces(): void
    {
        $categoria = CategoriaCarta::first();
        $tipo = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Comun', 'nombre' => 'Chute Extra',
            'descripcion' => 'test', 'codigo_efecto' => 'JUG-COM-CHUTE', 'activa' => true,
        ]);

        CodigoCanje::create([
            'codigo' => 'UNAVEZ', 'nombre' => 'test', 'tipo_premio' => 'carta_especifica',
            'id_tipo_carta' => $tipo->id, 'activo' => true,
        ]);

        $this->actingAs($this->usuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'UNAVEZ'])->assertOk();
        $this->actingAs($this->usuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'UNAVEZ'])->assertStatus(409);

        $this->assertSame(1, CartaUsuario::where('id_usuario', $this->usuario->id)->count());
    }

    public function test_un_codigo_agotado_ya_no_se_puede_canjear(): void
    {
        $categoria = CategoriaCarta::first();
        $tipo = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Comun', 'nombre' => 'Chute Extra',
            'descripcion' => 'test', 'codigo_efecto' => 'JUG-COM-CHUTE', 'activa' => true,
        ]);

        $codigo = CodigoCanje::create([
            'codigo' => 'LIMITADO', 'nombre' => 'test', 'tipo_premio' => 'carta_especifica',
            'id_tipo_carta' => $tipo->id, 'usos_maximos' => 1, 'activo' => true,
        ]);

        $this->actingAs($this->usuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'LIMITADO'])->assertOk();

        $segundoUsuario = User::factory()->create(['liga_activa_id' => $this->liga->id]);
        $this->actingAs($segundoUsuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'LIMITADO'])->assertStatus(410);
    }

    public function test_un_codigo_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario)->postJson('/api/v1/codigos/canjear', ['codigo' => 'NOEXISTE'])->assertStatus(404);
    }

    public function test_no_se_puede_canjear_sin_liga_con_cartas_activadas(): void
    {
        $ligaNormal = Liga::factory()->create(['id_temporada' => $this->liga->id_temporada, 'tipo' => 'Normal']);
        $usuarioSinCartas = User::factory()->create(['liga_activa_id' => $ligaNormal->id]);

        CodigoCanje::create([
            'codigo' => 'PRUEBA', 'nombre' => 'test', 'tipo_premio' => 'tirada_aleatoria', 'activo' => true,
        ]);

        $this->actingAs($usuarioSinCartas)->postJson('/api/v1/codigos/canjear', ['codigo' => 'PRUEBA'])->assertStatus(422);
    }
}