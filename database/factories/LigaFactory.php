<?php

namespace Database\Factories;

use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LigaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->words(2, true),
            'codigo_acceso' => strtoupper(Str::random(6)),
            'id_temporada' => Temporada::factory(),
            'id_usuario_creador' => User::factory(),
        ];
    }
}