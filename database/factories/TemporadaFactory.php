<?php

namespace Database\Factories;

use App\Models\Competicion;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemporadaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_competicion' => Competicion::factory(),
            'nombre' => '2026-2027',
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2027-05-25',
        ];
    }
}