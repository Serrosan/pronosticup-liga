<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mis Cartas → Historial: la ruta existe y responde (se quedó sin registrar y la pestaña salía vacía). */
class HistorialCartasRutaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_historial_de_cartas_responde(): void
    {
        $liga = Liga::factory()->create(['id_temporada' => Temporada::factory()->create()->id, 'tipo' => 'ConExtras']);
        $usuario = User::factory()->create(['liga_activa_id' => $liga->id]);
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Miembro']);

        $this->actingAs($usuario)
            ->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost', 'Accept' => 'application/json'])
            ->getJson('/api/v1/mis-cartas/historial?pagina=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['pagina', 'hay_mas']]);
    }
}
