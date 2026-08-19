<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Trofeo;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class TrofeosSeeder extends Seeder
{
    public function run(): void
    {
        Trofeo::query()->delete();

        $filas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'))[8]; // TROFEOS
        array_shift($filas); // quita la fila de cabecera

        foreach ($filas as $fila) {
            // saltamos filas vacías Y la fila de ejemplo (donde el id no es numérico)
            if (empty($fila[0]) || ! is_numeric($fila[0])) continue;

            Trofeo::create([
                'nombre' => $fila[1],
                'tipo' => $fila[2],
                'ambito' => $fila[3],
                'logo' => $fila[4],
                'imagen' => $fila[5],
            ]);
        }

        $this->command->info('Trofeos importados: '.Trofeo::count());
    }
}