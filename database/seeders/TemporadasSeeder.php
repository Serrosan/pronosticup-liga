<?php

namespace Database\Seeders;

use App\Models\Competicion;
use App\Models\Temporada;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class TemporadasSeeder extends Seeder
{
    use ConvierteFechasExcel;

    public function run(): void
    {
        $competicion = Competicion::firstOrCreate(
            ['codigo_externo' => 'PD'],
            ['nombre' => 'Primera Division', 'pais' => 'España']
        );

        Temporada::query()->delete();

        $filas = Excel::toArray(new \App\Imports\LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'))[7];
        array_shift($filas);

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue;

            Temporada::create([
                'id_competicion' => $competicion->id,
                'nombre' => $fila[1],
                'fecha_inicio' => $this->fechaExcel($fila[2]),
                'fecha_fin' => $this->fechaExcel($fila[3]),
                'logo' => $fila[4] ?? null,
            ]);
        }

        $this->command->info('Temporadas importadas: '.Temporada::count());
    }
}