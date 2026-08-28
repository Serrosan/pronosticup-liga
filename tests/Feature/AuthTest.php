<?php

namespace Tests\Feature;

use App\Models\Liga;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_se_puede_registrar(): void
    {
        Notification::fake();

        $respuesta = $this->postJson('/api/v1/register', [
            'name' => 'Sergio Prueba',
            'email' => 'sergio@ejemplo.com',
            'password' => 'contraseñaSegura123',
            'password_confirmation' => 'contraseñaSegura123',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'sergio@ejemplo.com']);
        Notification::assertSentTo(User::first(), VerifyEmail::class);
    }

    public function test_registrarse_con_un_codigo_de_liga_valido_te_une_automaticamente(): void
    {
        Notification::fake();
        $temporada = Temporada::factory()->create();
        $liga = Liga::factory()->create(['id_temporada' => $temporada->id, 'codigo_acceso' => 'FAMIL1']);

        $this->postJson('/api/v1/register', [
            'name' => 'Nuevo Miembro',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'contraseñaSegura123',
            'password_confirmation' => 'contraseñaSegura123',
            'codigo_liga' => 'famil1',
        ]);

        $usuario = User::where('email', 'nuevo@ejemplo.com')->first();
        $this->assertDatabaseHas('liga_usuario', ['id_usuario' => $usuario->id, 'id_liga' => $liga->id]);
    }

    public function test_un_usuario_no_activado_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('miContraseña123'),
            'activado_en' => null,
        ]);

        $respuesta = $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'miContraseña123',
        ]);

        $respuesta->assertStatus(403);
    }

    public function test_un_usuario_activado_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('miContraseña123'),
            'activado_en' => now(),
        ]);

        $respuesta = $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'miContraseña123',
        ]);

        $respuesta->assertStatus(200);
    }

    public function test_credenciales_incorrectas_dan_401(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('miContraseña123'),
            'activado_en' => now(),
        ]);

        $respuesta = $this->withHeaders(['Origin' => 'http://localhost:5173'])->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'contraseñaEquivocada',
        ]);

        $respuesta->assertStatus(401);
    }

    public function test_un_usuario_puede_cerrar_sesion(): void
    {
        $usuario = User::factory()->create(['activado_en' => now()]);

        $respuesta = $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->actingAs($usuario)
            ->postJson('/api/v1/logout');

        $respuesta->assertStatus(200);
    }

    public function test_una_ruta_protegida_devuelve_401_sin_sesion(): void
    {
        $respuesta = $this->getJson('/api/v1/me');

        $respuesta->assertStatus(401);
    }

    public function test_me_devuelve_los_datos_del_usuario_autenticado(): void
    {
        $usuario = User::factory()->create(['activado_en' => now()]);

        $respuesta = $this->actingAs($usuario)->getJson('/api/v1/me');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('data.id', $usuario->id);
    }
}