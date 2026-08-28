<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EstadioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Estadio '.$this->faker->lastName(),
            'ciudad' => $this->faker->city(),
            'capacidad' => $this->faker->numberBetween(15000, 90000),
        ];
    }
}