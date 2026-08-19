<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Estadio;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class EstadiosSeeder extends Seeder
{
    public function run(): void
    {
        Estadio::query()->delete();

        $filas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'))[2]; // pestaña ESTADIOS
        array_shift($filas);

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue;

            Estadio::create([
                'nombre' => $fila[1],
                'tamanio_campo' => $fila[2],
                'capacidad' => $fila[3],
                'ciudad' => $fila[4], // "lugar" en tu Excel, lo guardamos en nuestra columna "ciudad"
                'anio_construccion' => $fila[5],
                'anio_ult_remodelacion' => $fila[6],
            ]);
        }

        $this->command->info('Estadios importados: '.Estadio::count());
    }
}