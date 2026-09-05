<?php

namespace Database\Seeders;

use App\Models\ConfiguracionPuntos;
use Illuminate\Database\Seeder;

class ConfiguracionPuntosSeeder extends Seeder
{
    public function run(): void
    {
        ConfiguracionPuntos::firstOrCreate(['id_liga' => null]);
    }
}