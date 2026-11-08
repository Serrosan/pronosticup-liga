<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FootballDataService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.football_data.token');
    }

    public function obtenerTemporadaCompleta(): array
    {
        return $this->pedir([]);
    }

    public function obtenerJornada(int $jornada): array
    {
        return $this->pedir(['matchday' => $jornada]);
    }

    /**
     * Si football-data responde con error (límite de peticiones, token caducado,
     * caída) se sigue devolviendo una lista vacía, como siempre, pero queda
     * apuntado como aviso en el registro (Sistema → Errores, "mostrar avisos").
     * Antes se perdía en silencio y los partidos se quedaban sin actualizar.
     */
    private function pedir(array $parametros): array
    {
        $respuesta = Http::withHeaders(['X-Auth-Token' => $this->token])
            ->get('https://api.football-data.org/v4/competitions/PD/matches', $parametros);

        if (! $respuesta->successful()) {
            $detalle = mb_substr((string) ($respuesta->json('message') ?? $respuesta->body()), 0, 150);
            Log::warning("football-data respondió {$respuesta->status()} al pedir ".json_encode($parametros).": {$detalle}");

            return [];
        }

        return $respuesta->json('matches') ?? [];
    }
}