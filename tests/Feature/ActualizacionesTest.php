<?php

namespace Tests\Feature;

use App\Models\Actualizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualizacionesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['es_superadmin' => true])->save();

        return $u;
    }

    public function test_arranca_con_el_historial_y_el_balance_de_cartas(): void
    {
        $this->assertTrue(Actualizacion::where('slug', 'balance-cartas-1')->where('tipo', 'proximamente')->where('publicada', true)->exists());
        $this->assertGreaterThan(10, Actualizacion::where('tipo', 'hecho')->count());
    }

    public function test_los_jugadores_solo_ven_lo_publicado_y_proximamente_primero(): void
    {
        Actualizacion::create(['slug' => 'borrador', 'tipo' => 'hecho', 'fecha' => '2030-01-01', 'titulo' => 'Secreto', 'puntos' => ['x'], 'publicada' => false]);

        $respuesta = $this->actingAs(User::factory()->create())->getJson('/api/v1/actualizaciones')->assertOk();

        $this->assertNotContains('Secreto', array_column($respuesta->json('data'), 'titulo'));
        $this->assertSame('proximamente', $respuesta->json('data.0.tipo'));
        $this->assertNotEmpty($respuesta->json('marca'));
    }

    public function test_publicar_algo_cambia_la_marca_del_nuevo(): void
    {
        $antes = $this->actingAs(User::factory()->create())->getJson('/api/v1/actualizaciones')->json('marca');

        $this->actingAs($this->admin())->postJson('/api/v1/admin/actualizaciones', [
            'tipo' => 'hecho', 'fecha' => '2026-11-01', 'emoji' => '✨', 'titulo' => 'Algo nuevo',
            'puntos' => ['Una mejora'], 'notas' => [], 'publicada' => true,
        ])->assertCreated();

        $this->assertNotSame($antes, $this->getJson('/api/v1/actualizaciones')->json('marca'));
    }

    public function test_el_admin_crea_y_edita_un_proximamente(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->postJson('/api/v1/admin/actualizaciones', [
            'tipo' => 'proximamente', 'fecha' => '2026-11-01', 'titulo' => 'Más cambios', 'entra_en_vigor' => 'Jornada 12',
            'puntos' => [['carta' => 'Chute Extra', 'antes' => '+1', 'despues' => '+2', 'porque' => 'Porque sí', 'basura' => 'x']],
            'notas' => ['Una nota', ''], 'publicada' => false,
        ])->assertCreated()->json('data.id');

        $guardada = Actualizacion::find($id);
        $this->assertSame([['carta' => 'Chute Extra', 'antes' => '+1', 'despues' => '+2', 'porque' => 'Porque sí']], $guardada->puntos);
        $this->assertSame(['Una nota'], $guardada->notas);
        $this->assertFalse($guardada->publicada);

        $this->actingAs($admin)->putJson("/api/v1/admin/actualizaciones/{$id}", [
            'tipo' => 'hecho', 'fecha' => '2026-11-08', 'titulo' => 'Más cambios', 'puntos' => ['Chute Extra: +1 → +2'], 'notas' => [], 'publicada' => true,
        ])->assertOk();

        $this->assertSame('hecho', $guardada->fresh()->tipo);
        $this->assertTrue($guardada->fresh()->publicada);
    }

    public function test_un_cambio_sin_titulo_no_se_guarda(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/admin/actualizaciones', [
            'tipo' => 'proximamente', 'fecha' => '2026-11-01', 'titulo' => 'X', 'puntos' => [['carta' => '', 'antes' => 'a']], 'publicada' => false,
        ])->assertStatus(422);
    }

    public function test_un_jugador_no_puede_editar(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/admin/actualizaciones', [
            'tipo' => 'hecho', 'fecha' => '2026-11-01', 'titulo' => 'X', 'puntos' => ['y'], 'publicada' => true,
        ])->assertForbidden();
    }
}
