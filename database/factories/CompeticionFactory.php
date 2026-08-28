<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompeticionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'LaLiga',
            'pais' => 'España',
            'codigo_externo' => 'PD',
        ];
    }
}