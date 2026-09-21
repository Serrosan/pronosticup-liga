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

    public function test_expulsion_rara_solo_bloquea_faltas_no_jugadas(): void
    {
        [$liga, $tipoCartaSilbato] = $this->crearLigaConCartaFalta();
        $categoriaExpulsion = $tipoCartaSilbato->categoria;

        $tipoExpulsionRara = \App\Models\TipoCarta::create([
            'id_categoria' => $categoriaExpulsion->id, 'rareza' => 'Rara', 'nombre' => 'Expulsión',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-RAR-EXPULSION', 'activa' => true,
        ]);

        $atacante = User::factory()->create();
        $victima = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoExpulsionRara->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(MotorFaltas::class);

        $this->assertTrue($motor->estaExpulsadoDe($liga->id, $victima->id, 6, 'Faltas'));
        $this->assertFalse($motor->estaExpulsadoDe($liga->id, $victima->id, 6, 'Jugadas'));
    }

    public function test_bloquea_si_ya_no_es_posible_cumplir_el_requisito(): void
    {
        [$liga, $tipoCartaSilbato] = $this->crearLigaConCartaFalta();
        $categoria = $tipoCartaSilbato->categoria;

        $tipoRequisito = \App\Models\TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Comun', 'nombre' => 'Todo queda en casa',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-COM-CASA', 'activa' => true,
        ]);

        $atacante = User::factory()->create();
        $victima = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoRequisito->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(\App\Services\MotorFaltas::class);

        // 10 partidos en la jornada, ya llevas 9 pronosticados y 0 son "Local"
        // → solo queda 1 partido sin tocar, 0+1 < 2 → debe bloquear.
        $tipos = collect(array_fill(0, 8, 'Visitante'))->push('Visitante'); // 9 en total, ninguno Local

        $resultado = $motor->comprobarRequisitoPronostico($liga->id, $victima->id, 6, 10, 9, $tipos);

        $this->assertNotNull($resultado);
        $this->assertStringContainsString('imposible', $resultado);
    }

    public function test_permite_si_aun_es_posible_cumplir_el_requisito(): void
    {
        [$liga, $tipoCartaSilbato] = $this->crearLigaConCartaFalta();
        $categoria = $tipoCartaSilbato->categoria;

        $tipoRequisito = \App\Models\TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'Comun', 'nombre' => 'Todo queda en casa',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-COM-CASA', 'activa' => true,
        ]);

        $atacante = User::factory()->create();
        $victima = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoRequisito->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(\App\Services\MotorFaltas::class);

        // 10 partidos, llevas 8 pronosticados (0 Local) → quedan 2 sin tocar,
        // 0+2 = 2, justo el mínimo → debe permitirlo.
        $tipos = collect(array_fill(0, 8, 'Visitante'));

        $resultado = $motor->comprobarRequisitoPronostico($liga->id, $victima->id, 6, 10, 8, $tipos);

        $this->assertNull($resultado);
    }

    public function test_fallo_clamoroso_bloquea_al_top_3_goleadores_reales(): void
    {
        [$liga, $tipoCartaSilbato] = $this->crearLigaConCartaFalta();
        $categoria = $tipoCartaSilbato->categoria;

        $tipoFallo = \App\Models\TipoCarta::create([
            'id_categoria' => $categoria->id, 'rareza' => 'PocoComun', 'nombre' => 'Fallo clamoroso',
            'descripcion' => 'test', 'codigo_efecto' => 'FAL-PCOM-FALLO', 'activa' => true,
        ]);

        $atacante = User::factory()->create();
        $victima = User::factory()->create();

        CartaUsuario::create([
            'id_usuario' => $atacante->id, 'id_liga' => $liga->id, 'id_tipo_carta' => $tipoFallo->id,
            'jornada_obtenida' => 5, 'obtenida_en' => now(), 'origen' => 'manual', 'estado' => 'jugada',
            'id_usuario_objetivo' => $victima->id, 'jornada_efecto' => 6, 'jugada_en' => now(),
        ]);

        $motor = app(\App\Services\MotorFaltas::class);

        $this->assertTrue($motor->tieneFalloClamorosoActivo($liga->id, $victima->id, 6));
        $this->assertFalse($motor->tieneFalloClamorosoActivo($liga->id, $victima->id, 7));
    }
}