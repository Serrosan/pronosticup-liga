<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Arbitro;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ArbitrosSeeder extends Seeder
{
    use ConvierteFechasExcel;

    public function run(): void
    {
        Arbitro::query()->delete();

        $filas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'))[6]; // ARBITROS
        array_shift($filas);

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue;

            Arbitro::create([
                'nombre' => $fila[1],
                'apellidos' => $fila[2],
                'comunidad_autonoma' => $fila[3],
                'anio_debut' => $this->numeroExcel($fila[4]),
                'promedio_tarjetas_amarillas' => $this->decimalExcel($fila[5]),
                'promedio_tarjetas_rojas' => $this->decimalExcel($fila[6]),
                'imagen' => $fila[7],
            ]);
        }

        $this->command->info('Arbitros importados: '.Arbitro::count());
    }
}