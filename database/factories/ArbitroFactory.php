<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ArbitroFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'nacionalidad' => 'España',
        ];
    }
}