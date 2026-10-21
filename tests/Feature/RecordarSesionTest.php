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

    private function usuario(?string $tokenRecordar = null): User
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('secreto-123'),
            'activado_en' => now(),
            'desactivada_en' => null,
        ]);

        if ($tokenRecordar) {
            $usuario->forceFill(['remember_token' => $tokenRecordar])->save();
        }

        return $usuario;
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
        $usuario = $this->usuario('token-de-prueba');
        $valor = $usuario->id.'|token-de-prueba|hash';

        $respuesta = $this->actingAs($usuario)
            ->withHeaders($this->cabeceras())
            ->withCredentials()
            ->withCookie($this->cookieRecordar(), $valor)
            ->getJson('/api/v1/me');

        $respuesta->assertOk()->assertCookie($this->cookieRecordar(), $valor);
        $this->assertCaducaEnTreintaDias($respuesta);
    }

    public function test_una_cookie_vieja_no_se_renueva(): void
    {
        $usuario = $this->usuario('token-nuevo');

        $this->actingAs($usuario)
            ->withHeaders($this->cabeceras())
            ->withCredentials()
            ->withCookie($this->cookieRecordar(), $usuario->id.'|token-viejo|hash')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertCookieMissing($this->cookieRecordar());
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
        $usuario = $this->usuario('token-de-prueba');

        $this->actingAs($usuario)
            ->withHeaders($this->cabeceras())
            ->withCredentials()
            ->withCookie($this->cookieRecordar(), $usuario->id.'|token-de-prueba|hash')
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertCookieExpired($this->cookieRecordar());
    }

    public function test_si_llega_duplicada_se_borra_la_copia_vieja_del_host(): void
    {
        // Como en producción: la cookie buena va con dominio y la vieja, sin él.
        $this->app->make('cookie')->setDefaultPathAndDomain('/', 'localhost', false, 'lax');

        $usuario = $this->usuario();
        $nombre = $this->cookieRecordar();

        $respuesta = $this->withHeaders($this->cabeceras() + ['Cookie' => "{$nombre}=vieja; {$nombre}=buena"])
            ->postJson('/api/v1/login', [
                'email' => $usuario->email,
                'password' => 'secreto-123',
                'recordar' => true,
            ]);

        $respuesta->assertOk();

        $borradas = collect($respuesta->headers->getCookies())
            ->filter(fn ($c) => $c->getName() === $nombre && $c->getDomain() === null && $c->isCleared());

        $this->assertCount(1, $borradas, 'Debería borrar la copia vieja (solo host) del recuérdame.');
    }
}
