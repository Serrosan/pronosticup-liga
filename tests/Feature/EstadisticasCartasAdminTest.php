<?php

namespace Tests\Feature;

use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadisticasCartasAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_cuenta_salidas_uso_cumplimiento_y_puntos_por_carta(): void
    {
        $liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'ConExtras']);
        $admin = User::factory()->create();
        $admin->forceFill(['es_superadmin' => true])->save();
        $jugador = User::factory()->create();

        $jugadas = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);
        $chute = TipoCarta::create(['id_categoria' => $jugadas->id, 'rareza' => 'Comun', 'nombre' => 'Chute Extra', 'descripcion' => 'x', 'codigo_efecto' => 'JUG-COM-CHUTE', 'activa' => true]);
        TipoCarta::create(['id_categoria' => $jugadas->id, 'rareza' => 'Legendaria', 'nombre' => 'Crack', 'descripcion' => 'x', 'codigo_efecto' => 'JUG-LEG-CRACK', 'activa' => true]);

        $carta = fn (string $estado, int $puntos = 0) => CartaUsuario::create([
            'id_usuario' => $jugador->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $chute->id, 'jornada_obtenida' => 10,
            'obtenida_en' => now(), 'revelada_en' => now(), 'origen' => 'reparto_semanal', 'estado' => $estado,
            'jornada_efecto' => $estado === 'en_mano' ? null : 11, 'jugada_en' => $estado === 'en_mano' ? null : now(), 'puntos_generados' => $puntos,
        ]);
        $carta('resuelta_cumplida', 2);
        $carta('resuelta_no_cumplida');
        $carta('en_mano');
        $carta('en_mano');

        $datos = $this->actingAs($admin)->getJson('/api/v1/admin/estadisticas-cartas?id_liga='.$liga->id)->assertOk()->json('data');

        $fila = collect($datos['por_tipo'])->firstWhere('codigo', 'JUG-COM-CHUTE');
        $this->assertSame([4, 2, 2, 50, 50, 2], [$fila['salidas'], $fila['en_mano'], $fila['jugadas'], $fila['uso'], $fila['acierto'], $fila['puntos']]);
        $this->assertContains('JUG-LEG-CRACK', array_column($datos['nunca_salidas'], 'codigo'));
        $this->assertSame(4, $datos['por_origen'][0]['total']);
        $this->assertSame(1, $datos['resumen']['jornadas']);
    }

    public function test_un_jugador_no_puede_verlas(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/estadisticas-cartas')->assertForbidden();
    }
}
