<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisarPeticionLenta;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La portada da lo mismo que antes (ahora con menos consultas) y las
 * peticiones lentas quedan apuntadas como aviso.
 */
class RendimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        AvisarPeticionLenta::$umbralMs = 1000;
        parent::tearDown();
    }

    private function ligaConDos(): array
    {
        $liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'Normal']);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bea = User::factory()->create(['name' => 'Bea']);
        foreach ([$ana, $bea] as $u) {
            $u->forceFill(['liga_activa_id' => $liga->id])->save();
            $liga->usuarios()->attach($u->id, ['rol' => 'Admin', 'se_unio_en' => now()]);
        }

        return [$liga, $ana, $bea];
    }

    private function puntos(Liga $liga, User $u, int $jornada, int $puntos): void
    {
        EventoPuntos::create(['id_usuario' => $u->id, 'id_liga' => $liga->id, 'id_partido' => null, 'jornada' => $jornada, 'tipo_evento' => 'BonusPleno', 'puntos' => $puntos]);
    }

    public function test_la_grafica_de_evolucion_acumula_igual_que_siempre(): void
    {
        [$liga, $ana, $bea] = $this->ligaConDos();
        $this->puntos($liga, $ana, 1, 5);
        $this->puntos($liga, $ana, 1, 2);
        $this->puntos($liga, $ana, 2, 3);
        $this->puntos($liga, $bea, 2, 4);
        Sanctum::actingAs($ana);

        $evolucion = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.evolucion');

        $this->assertSame([1, 2], $evolucion['jornadas']);
        $series = collect($evolucion['series'])->keyBy('usuario');
        $this->assertSame([7, 10], $series['Ana']['datos']);
        $this->assertSame([0, 4], $series['Bea']['datos']);
    }

    public function test_el_empate_en_el_liderato_sigue_saliendo(): void
    {
        [$liga, $ana, $bea] = $this->ligaConDos();
        $this->puntos($liga, $ana, 1, 6);
        $this->puntos($liga, $bea, 1, 6);
        Sanctum::actingAs($ana);

        $empate = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.empate_liderato');

        $this->assertSame(6, $empate['puntos']);
        $this->assertEqualsCanonicalizing(['Ana', 'Bea'], $empate['usuarios']);
    }

    public function test_una_peticion_lenta_queda_apuntada_como_aviso(): void
    {
        AvisarPeticionLenta::$umbralMs = 0; // todo cuenta como lento
        Log::spy();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me')->assertOk();

        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje) => str_starts_with($mensaje, 'Petición lenta: GET /api/v1/me'))->once();
    }
}
