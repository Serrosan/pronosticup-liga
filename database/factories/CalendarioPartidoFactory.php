<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarioPartidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jornada' => 1,
            'horario_estimado' => now(),
            'estado' => 'Programado',
        ];
    }
}