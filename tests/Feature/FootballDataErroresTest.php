<?php

namespace Tests\Feature;

use App\Services\FootballDataService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Si football-data responde con error, queda en el registro (antes se perdía en silencio). */
class FootballDataErroresTest extends TestCase
{
    public function test_un_error_de_la_api_queda_apuntado_y_devuelve_lista_vacia(): void
    {
        config(['services.football_data.token' => 'prueba']);
        Http::fake(['api.football-data.org/*' => Http::response(['message' => 'You reached your request limit.'], 429)]);
        Log::spy();

        $this->assertSame([], app(FootballDataService::class)->obtenerJornada(12));

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($mensaje) => str_contains($mensaje, 'football-data') && str_contains($mensaje, '429'));
    }

    public function test_una_respuesta_buena_no_apunta_nada(): void
    {
        config(['services.football_data.token' => 'prueba']);
        Http::fake(['api.football-data.org/*' => Http::response(['matches' => [['id' => 1]]], 200)]);
        Log::spy();

        $this->assertSame([['id' => 1]], app(FootballDataService::class)->obtenerJornada(12));

        Log::shouldNotHaveReceived('warning');
    }
}
