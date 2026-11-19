<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una petición sin sesión a la API tiene que dar 401, nunca un 500.
 * Pasaba con peticiones que no piden JSON (un enlace abierto en el navegador, un
 * <audio>/<img> que apunta a la API con la sesión caducada, bots): Laravel intentaba
 * redirigir a la ruta "login", que no existe en una SPA → RouteNotFoundException.
 */
class NoAutenticadoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_y_sin_pedir_json_da_401_y_no_500(): void
    {
        $this->get('/api/v1/mis-cartas/historial')->assertUnauthorized();
    }

    public function test_sin_sesion_pidiendo_json_sigue_dando_401(): void
    {
        $this->getJson('/api/v1/mis-cartas/historial')->assertUnauthorized();
    }
}
