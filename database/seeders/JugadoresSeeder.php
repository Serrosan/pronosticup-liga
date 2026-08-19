<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class JugadoresSeeder extends Seeder
{
    use ConvierteFechasExcel;

    public function run(): void
    {
        // Orden importante: primero borramos plantilla_temporada (depende de jugadores),
        // luego jugadores. Al revés, MySQL lo bloquearía por la clave foránea.
        PlantillaTemporada::query()->delete();
        Jugador::query()->delete();

        $temporada = Temporada::first();

        $filas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'))[3]; // JUGADORES
        array_shift($filas);

        $creados = 0;
        $sinEquipo = 0;

        $mapaEquipos = [
            'Alaves' => 'Deportivo Alavés',
            'Athletic Club De Bilbao' => 'Athletic Club',
            'Atletico De Madrid' => 'Club Atlético de Madrid',
            'Barcelona' => 'Fútbol Club Barcelona',
            'Betis' => 'Real Betis Balompié',
            'Celta Vigo' => 'Real Club Celta de Vigo',
            'Deportivo De La Coruña' => 'RC Deportivo de A Coruña',
            'Elche' => 'Elche Club de Fútbol',
            'Espanyol De Barcelona' => 'RCD Espanyol de Barcelona',
            'Getafe Fc' => 'Getafe Club de Fútbol',
            'Levante Ud' => 'Levante Unión Deportiva',
            'Málaga' => 'Málaga Club de Fútbol',
            'Osasuna' => 'Club Atlético Osasuna',
            'Racing De Santander' => 'Real Racing Club de Santander',
            'Rayo Vallecano' => 'Rayo Vallecano de Madrid',
            'Real Madrid' => 'Real Madrid Club de Fútbol',
            'Real Sociedad' => 'Real Sociedad de Fútbol',
            'Sevilla' => 'Sevilla Fútbol Club',
            'Valencia' => 'Valencia Club de Fútbol',
            'Villareal' => 'Villarreal Club de Fútbol', // ojo: "Villareal" (sin la segunda r) es como está escrito en tu Excel
        ];

        foreach ($filas as $fila) {
            if (empty($fila[1])) continue; // sin nombre, fila vacía

            $jugador = Jugador::create([
                'nombre' => $fila[1],
                'apellidos' => $fila[2],
                'nombre_camiseta' => $fila[3],
                'posicion' => $fila[5],
                'posicion_detallada' => $fila[6],
                'fecha_nacimiento' => $this->fechaExcel($fila[7]),
                'lugar_nacimiento' => $fila[8],
                'nacionalidad' => $fila[8], // mismo valor por ahora, ver nota
                'altura' => $fila[9],
                'seleccion' => $fila[10],
                'pie' => $fila[11],
                'fecha_fin_contrato' => $this->fechaExcel($fila[12]),
                'club_anterior' => $fila[13],
            ]);

            $creados++;

            $nombreReal = $mapaEquipos[$fila[0]] ?? $fila[0];
            $equipo = Equipo::where('nombre', $nombreReal)->first();

            if ($equipo && $temporada) {
                PlantillaTemporada::create([
                    'id_jugador' => $jugador->id,
                    'id_equipo' => $equipo->id,
                    'id_temporada' => $temporada->id,
                    'dorsal' => $this->numeroExcel($fila[4]),
                ]);
            } else {
                $sinEquipo++;
            }
        }

        $this->command->info("Jugadores importados: {$creados}");
        $this->command->info("Sin equipo encontrado: {$sinEquipo}");
    }
}