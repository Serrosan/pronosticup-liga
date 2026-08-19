<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Entrenador;
use App\Models\Equipo;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class EntrenadoresSeeder extends Seeder
{
    use ConvierteFechasExcel;

    public function run(): void
    {
        Entrenador::query()->delete();

        $todasLasHojas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'));
        $filasEquipos = $todasLasHojas[1];
        array_shift($filasEquipos);

        $filas = $todasLasHojas[4]; // ENTRENADORES
        array_shift($filas);

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue;

            $idEquipoExcel = $fila[9];
            $equipo = null;
            if ($idEquipoExcel) {
                $filaEquipo = $filasEquipos[$idEquipoExcel - 1] ?? null;
                if ($filaEquipo) {
                    $equipo = Equipo::where('nombre', $filaEquipo[1])->first();
                }
            }

            Entrenador::create([
                'nombre' => $fila[1],
                'apellidos' => $fila[2],
                'fecha_nacimiento' => $this->fechaExcel($fila[3]),
                'imagen_foto' => $fila[4],
                'ciudad_nacimiento' => $fila[5],
                'pais_nacimiento' => $fila[6],
                'fecha_inicio_cargo' => $this->fechaExcel($fila[7]),
                'fecha_fin_cargo' => $this->fechaExcel($fila[8]),
                'id_equipo_actual' => $equipo?->id,
            ]);
        }

        $this->command->info('Entrenadores importados: '.Entrenador::count());
    }
}