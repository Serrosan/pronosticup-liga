<?php

namespace Tests\Feature;

use App\Models\PushSuscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SesionesOtrosDispositivosTest extends TestCase
{
    use RefreshDatabase;

    private const ESTE = 'https://push.example.com/este-movil';
    private const OTRO = 'https://push.example.com/movil-perdido';

    private function cabeceras(): array
    {
        return ['Origin' => 'http://localhost', 'Referer' => 'http://localhost', 'Accept' => 'application/json'];
    }

    /** Usuario con otras dos sesiones abiertas y avisos en dos dispositivos. */
    private function escenario(): User
    {
        $usuario = User::factory()->create(['password' => Hash::make('Secreto123'), 'activado_en' => now()]);
        $usuario->forceFill(['remember_token' => 'token-viejo'])->save();

        foreach (['sesion-ordenador-amigo', 'sesion-movil-perdido'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $usuario->id, 'ip_address' => '1.2.3.4',
                'user_agent' => 'prueba', 'payload' => '', 'last_activity' => time(),
            ]);
        }

        foreach ([self::ESTE, self::OTRO] as $endpoint) {
            PushSuscripcion::create([
                'id_usuario' => $usuario->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSuscripcion::huella($endpoint),
                'p256dh' => 'clave', 'auth' => 'auth',
            ]);
        }

        return $usuario;
    }

    private function assertSoloQuedaEsteDispositivo(User $usuario): void
    {
        $this->assertSame(0, DB::table('sessions')->whereIn('id', ['sesion-ordenador-amigo', 'sesion-movil-perdido'])->count());
        $this->assertNotSame('token-viejo', $usuario->fresh()->remember_token, 'El recuérdame de los otros dispositivos debe dejar de valer');
        $this->assertSame([PushSuscripcion::huella(self::ESTE)], PushSuscripcion::where('id_usuario', $usuario->id)->pluck('endpoint_hash')->all());
    }

    public function test_el_boton_cierra_las_demas_sesiones_y_sus_avisos(): void
    {
        $usuario = $this->escenario();

        $this->actingAs($usuario)->withHeaders($this->cabeceras())
            ->postJson('/api/v1/profile/cerrar-otras-sesiones', ['password' => 'Secreto123', 'endpoint' => self::ESTE])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Se ha cerrado la sesión en tus demás dispositivos. Los avisos al móvil de otro dispositivo se han desactivado.']);

        $this->assertSoloQuedaEsteDispositivo($usuario);
    }

    public function test_con_la_contrasena_mal_no_se_cierra_nada(): void
    {
        $usuario = $this->escenario();

        $this->actingAs($usuario)->withHeaders($this->cabeceras())
            ->postJson('/api/v1/profile/cerrar-otras-sesiones', ['password' => 'NoEsEsta1', 'endpoint' => self::ESTE])
            ->assertStatus(422);

        $this->assertSame(2, DB::table('sessions')->where('user_id', $usuario->id)->count());
        $this->assertSame(2, PushSuscripcion::where('id_usuario', $usuario->id)->count());
        $this->assertSame('token-viejo', $usuario->fresh()->remember_token);
    }

    public function test_cambiar_la_contrasena_cierra_las_demas_sesiones(): void
    {
        $usuario = $this->escenario();

        $this->actingAs($usuario)->withHeaders($this->cabeceras())
            ->postJson('/api/v1/profile/password', [
                'current_password' => 'Secreto123',
                'password' => 'NuevaClave456',
                'password_confirmation' => 'NuevaClave456',
                'endpoint' => self::ESTE,
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NuevaClave456', $usuario->fresh()->password));
        $this->assertSoloQuedaEsteDispositivo($usuario);
    }

    public function test_sin_avisos_en_este_dispositivo_se_quitan_todos_los_avisos(): void
    {
        $usuario = $this->escenario();

        $this->actingAs($usuario)->withHeaders($this->cabeceras())
            ->postJson('/api/v1/profile/cerrar-otras-sesiones', ['password' => 'Secreto123'])
            ->assertOk();

        $this->assertSame(0, PushSuscripcion::where('id_usuario', $usuario->id)->count());
    }
}
