<?php

namespace Tests\Feature;

use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use App\Services\MotorFaltas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorFaltasTest extends TestCase
{
    use RefreshDatabase;

    private function crearLigaConCartaFalta(): array
    {
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'tipo' => 'ConExtras']);
        $categoria = CategoriaCarta::create(['nombre' => 'Faltas', 'activa' => true]);
        $tipoCarta = TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Comun', 'nombre' => 'Silbato',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-COM-SILBATO', 'activa' => true,
        ]);

        return [$liga, $tipoCarta];
    }

    public function test_no_deja_repetir_rival_2_semanas_seguidas(): void
    {
        [$liga, $tipoCarta] = $this->crearLigaConCartaFalta();
        $atacante = User::factory()->create();
        $objetivo = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoCarta->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $objetivo->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(MotorFaltas::class);
        $resultado = $motor->comprobarAntiAbuso($liga->id, $atacante->id, $objetivo->id, 7, 10);

        $this->assertNotNull($resultado);
        $this->assertStringContainsString('2 semanas seguidas', $resultado);
    }

    public function test_deja_repetir_rival_si_no_fue_la_semana_justo_anterior(): void
    {
        [$liga, $tipoCarta] = $this->crearLigaConCartaFalta();
        $atacante = User::factory()->create();
        $objetivo = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoCarta->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $objetivo->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(MotorFaltas::class);
        $resultado = $motor->comprobarAntiAbuso($liga->id, $atacante->id, $objetivo->id, 9, 10);

        $this->assertNull($resultado);
    }

    public function test_no_deja_superar_el_maximo_de_faltas_recibidas(): void
    {
        [$liga, $tipoCarta] = $this->crearLigaConCartaFalta();
        $objetivo = User::factory()->create();

        // Liga de 4 miembros → máximo 2 Faltas recibidas por jornada (intdiv(4,2))
        for ($i = 0; $i < 2; $i++) {
            $otroAtacante = User::factory()->create();
            CartaUsuario::create([
                'id_usuario' => $otroAtacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoCarta->id,
                'jornada_obtenida' => 6, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
                'id_usuario_objetivo' => $objetivo->id, 'jornada_efecto' => 7, 'jugada_en' => now(),
            ]);
        }

        $nuevoAtacante = User::factory()->create();
        $motor = app(MotorFaltas::class);
        $resultado = $motor->comprobarAntiAbuso($liga->id, $nuevoAtacante->id, $objetivo->id, 7, 4);

        $this->assertNotNull($resultado);
        $this->assertStringContainsString('máximo de Faltas recibidas', $resultado);
    }
}