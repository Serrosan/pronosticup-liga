<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecordarSesionTest extends TestCase
{
    use RefreshDatabase;

    private const TREINTA_DIAS = 43200;

    private function cabeceras(): array
    {
        // Petición "desde el frontend" para que Sanctum use sesión y cookies.
        return ['Origin' => 'http://localhost', 'Referer' => 'http://localhost', 'Accept' => 'application/json'];
    }

    private function usuario(): User
    {
        return User::factory()->create([
            'password' => Hash::make('secreto-123'),
            'activado_en' => now(),
            'desactivada_en' => null,
        ]);
    }

    private function cookieRecordar(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function assertCaducaEnTreintaDias($respuesta): void
    {
        $cookie = $respuesta->getCookie($this->cookieRecordar(), false);
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(
            now()->addMinutes(self::TREINTA_DIAS)->getTimestamp(),
            $cookie->getExpiresTime(),
            300
        );
    }

    public function test_con_la_casilla_marcada_recuerda_la_sesion_30_dias(): void
    {
        $usuario = $this->usuario();

        $respuesta = $this->withHeaders($this->cabeceras())->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'secreto-123',
            'recordar' => true,
        ]);

        $respuesta->assertOk()->assertCookie($this->cookieRecordar());
        $this->assertCaducaEnTreintaDias($respuesta);
        $this->assertNotNull($usuario->fresh()->remember_token);
    }

    public function test_sin_la_casilla_no_recuerda_la_sesion(): void
    {
        $usuario = $this->usuario();

        $this->withHeaders($this->cabeceras())->postJson('/api/v1/login', [
            'email' => $usuario->email,
            'password' => 'secreto-123',
        ])->assertOk()->assertCookieMissing($this->cookieRecordar());
    }

    public function test_cada_visita_renueva_los_30_dias(): void
    {
        $usuario = $this->usuario();
        $valor = $usuario->id.'|token-de-prueba|hash';

        $respuesta = $this->actingAs($usuario)
            ->withHeaders($this->cabeceras())
            ->withCredentials()
            ->withCookie($this->cookieRecordar(), $valor)
            ->getJson('/api/v1/me');

        $respuesta->assertOk()->assertCookie($this->cookieRecordar(), $valor);
        $this->assertCaducaEnTreintaDias($respuesta);
    }

    public function test_quien_no_marco_la_casilla_no_recibe_cookie_al_navegar(): void
    {
        $this->actingAs($this->usuario())
            ->withHeaders($this->cabeceras())
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertCookieMissing($this->cookieRecordar());
    }

    public function test_cerrar_sesion_borra_el_recordar(): void
    {
        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->withHeaders($this->cabeceras())
            ->withCredentials()
            ->withCookie($this->cookieRecordar(), $usuario->id.'|token-de-prueba|hash')
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertCookieExpired($this->cookieRecordar());
    }
}
