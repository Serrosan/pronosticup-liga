<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EquipoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company().' Club de Fútbol',
            'nombre_corto' => $this->faker->word(),
            'ciudad' => $this->faker->city(),
        ];
    }
}