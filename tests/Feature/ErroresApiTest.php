<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Los errores de la API llegan al jugador con su código y su mensaje, no como un 500 genérico. */
class ErroresApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]); // como en producción

        Route::get('/api/v1/_prueba/abort-403', fn () => abort(403, 'Solo el admin de la liga puede hacer esto.'));
        Route::get('/api/v1/_prueba/abort-403-sin-texto', fn () => abort(403));
        Route::get('/api/v1/_prueba/caducado', fn () => abort(419));
        Route::get('/api/v1/_prueba/grande', fn () => abort(413));
        Route::get('/api/v1/_prueba/roto', fn () => throw new \RuntimeException('detalle interno que no debe verse'));
    }

    public function test_un_abort_conserva_su_codigo_y_su_mensaje(): void
    {
        $this->getJson('/api/v1/_prueba/abort-403')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'Solo el admin de la liga puede hacer esto.']);
    }

    public function test_un_abort_sin_texto_lleva_un_mensaje_en_espanol(): void
    {
        $this->getJson('/api/v1/_prueba/abort-403-sin-texto')->assertStatus(403)
            ->assertExactJson(['message' => 'No tienes permiso para hacer esto.']);
        $this->getJson('/api/v1/_prueba/caducado')->assertStatus(419)
            ->assertExactJson(['message' => 'La página ha caducado. Recarga e inténtalo de nuevo.']);
        $this->getJson('/api/v1/_prueba/grande')->assertStatus(413)
            ->assertExactJson(['message' => 'El archivo es demasiado grande.']);
    }

    public function test_un_fallo_de_verdad_sigue_siendo_500_sin_detalles(): void
    {
        $this->getJson('/api/v1/_prueba/roto')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Ha ocurrido un error inesperado. Inténtalo de nuevo.']);
    }
}
