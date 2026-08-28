<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrofeoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->words(2, true),
            'tipo' => $this->faker->randomElement(['Colectivo', 'Individual']),
            'ambito' => $this->faker->randomElement(['Nacional', 'Internacional']),
        ];
    }
}