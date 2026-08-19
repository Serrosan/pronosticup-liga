<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Equipo;
use App\Models\Estadio;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class EquiposSeeder extends Seeder
{
    use ConvierteFechasExcel;

    public function run(): void
    {
        Equipo::query()->delete();

        $todasLasHojas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'));
        $filasEstadios = $todasLasHojas[2];
        array_shift($filasEstadios);

        $filas = $todasLasHojas[1];
        array_shift($filas);

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue;

            $idEstadioExcel = $fila[14];
            $estadio = null;
            if ($idEstadioExcel) {
                $filaEstadio = $filasEstadios[$idEstadioExcel - 1] ?? null;
                if ($filaEstadio) {
                    $estadio = Estadio::where('nombre', $filaEstadio[1])->first();
                }
            }

            Equipo::create([
                'nombre' => $fila[1],
                'nombre_corto' => $fila[2],
                'siglas' => $fila[3],
                'ciudad' => $fila[4],
                'num_socios' => $this->numeroExcel($fila[5]),
                'num_abonados' => $this->numeroExcel($fila[6]),
                'año_fundacion' => $this->numeroExcel($fila[7]),
                'escudo_url' => $fila[8],
                'color_primario' => $fila[9],
                'color_secundario' => $fila[10],
                'camiseta_1' => $fila[11],
                'camiseta_2' => $fila[12],
                'camiseta_3' => $fila[13],
                'id_estadio' => $estadio?->id,
            ]);
        }

        $this->command->info('Equipos importados: '.Equipo::count());
    }
}