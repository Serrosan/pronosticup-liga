<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventoCalendarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-15',
            'color' => '#FFB238',
        ];
    }
}