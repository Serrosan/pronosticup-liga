<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * ⚠️ NO EJECUTAR EN UN PROYECTO YA EN USO.
     * Todos los seeders llamados aquí BORRAN sus tablas antes de insertar
     * desde el Excel original — destruirían fotos, resultados reales,
     * y datos enriquecidos desde el admin/API. Solo sirve para el arranque
     * inicial de un servidor nuevo, antes de que haya datos reales.
     */
    public function run(): void
    {
        $this->call([
        /*    TemporadasSeeder::class,
            EstadiosSeeder::class,
            EquiposSeeder::class,
            EntrenadoresSeeder::class,
            ArbitrosSeeder::class,
            TrofeosSeeder::class,
            JugadoresSeeder::class,
            CalendarioPartidosSeeder::class,*/
        ]);
    }
}
