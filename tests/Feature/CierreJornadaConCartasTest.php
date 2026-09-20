<?php

namespace Tests\Feature;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\CierreJornada;
use App\Models\ConfiguracionPuntos;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\TipoCarta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CierreJornadaConCartasTest extends TestCase
{
    use RefreshDatabase;

    public function test_chute_extra_suma_su_bonus_al_cerrar_la_jornada(): void
    {
        $temporada = Temporada::factory()->create();

        $liga = Liga::factory()->create([
            'id_temporada' => $temporada->id,
            'tipo' => 'ConExtras',
        ]);

        $usuario = User::factory()->create();
        $liga->usuarios()->attach($usuario->id, ['rol' => 'Admin']);
        $usuario->update(['liga_activa_id' => $liga->id]);

        $equipoLocal = \App\Models\Equipo::factory()->create();
        $equipoVisitante = \App\Models\Equipo::factory()->create();

        $partido = CalendarioPartido::factory()->create([
            'id_temporada' => $temporada->id,
            'jornada' => 5,
            'id_equipo_local' => $equipoLocal->id,
            'id_equipo_visitante' => $equipoVisitante->id,
            'estado' => 'Jugado',
            'goles_casa' => 2,
            'goles_fuera' => 0,
        ]);

        ConfiguracionPuntos::create([
            'id_liga' => $liga->id,
            'puntos_signo' => 1,
            'puntos_diferencia' => 2,
            'puntos_exacto' => 5,
            'puntos_gol_goleador' => 1,
            'bonus_pleno_7' => 2,
            'bonus_pleno_8' => 4,
            'bonus_pleno_9' => 8,
            'bonus_pleno_10' => 15,
        ]);

        Pronostico::create([
            'id_usuario' => $usuario->id,
            'id_liga' => $liga->id,
            'id_partido' => $partido->id,
            'goles_local_predicho' => 1,
            'goles_visitante_predicho' => 0,
            'resultado_1x2' => 'Local',
            'enviado_en' => now(),
        ]);

        $categoria = CategoriaCarta::create(['nombre' => 'Jugadas', 'activa' => true]);

        $tipoCarta = TipoCarta::create([
            'id_categoria' => $categoria->id,
            'rareza' => 'Comun',
            'nombre' => 'Chute Extra',
            'descripcion' => '+1 punto en un partido de tu elección',
            'codigo_efecto' => 'JUG-COM-CHUTE',
            'activa' => true,
        ]);

        $carta = CartaUsuario::create([
            'id_usuario' => $usuario->id,
            'id_liga' => $liga->id,
            'id_tipo_carta' => $tipoCarta->id,
            'jornada_obtenida' => 5,
            'obtenida_en' => now(),
            'revelada_en' => now(),
            'origen' => 'manual',
            'estado' => 'jugada',
            'id_partido' => $partido->id,
            'jornada_efecto' => 5,
            'jugada_en' => now(),
        ]);

        // El pronóstico acertó signo (Local) pero no diferencia ni exacto → base = 1pt.
        // Con Chute Extra Común (+1) debería quedar en 2pt.
        $respuesta = $this->actingAs($usuario)->postJson('/api/v1/jornadas/5/cerrar');

        $respuesta->assertOk();

        $eventoDelPartido = EventoPuntos::where('id_partido', $partido->id)->where('id_usuario', $usuario->id)->first();

        $this->assertNotNull($eventoDelPartido);
        $this->assertSame('Acierto1x2', $eventoDelPartido->tipo_evento);
        $this->assertSame(2, $eventoDelPartido->puntos); // 1 base + 1 de Chute Extra
        $this->assertNull($eventoDelPartido->nota_carta);

        $carta->refresh();
        $this->assertSame('resuelta_cumplida', $carta->estado);
        $this->assertSame(1, $carta->puntos_generados);

        $this->assertTrue(CierreJornada::where('id_liga', $liga->id)->where('jornada', 5)->where('cerrada', true)->exists());
    }
}