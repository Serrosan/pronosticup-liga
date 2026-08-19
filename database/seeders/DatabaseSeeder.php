<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TemporadasSeeder::class,
            EstadiosSeeder::class,
            EquiposSeeder::class,
            EntrenadoresSeeder::class,
            ArbitrosSeeder::class,
            TrofeosSeeder::class,
            JugadoresSeeder::class,
            CalendarioPartidosSeeder::class,
        ]);
    }
}
