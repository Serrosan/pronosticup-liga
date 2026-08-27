<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FootballDataService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.football_data.token');
    }

    public function obtenerTemporadaCompleta(): array
    {
        $respuesta = Http::withHeaders(['X-Auth-Token' => $this->token])
            ->get('https://api.football-data.org/v4/competitions/PD/matches');

        return $respuesta->json('matches') ?? [];
    }

    public function obtenerJornada(int $jornada): array
    {
        $respuesta = Http::withHeaders(['X-Auth-Token' => $this->token])
            ->get('https://api.football-data.org/v4/competitions/PD/matches', ['matchday' => $jornada]);

        return $respuesta->json('matches') ?? [];
    }
}