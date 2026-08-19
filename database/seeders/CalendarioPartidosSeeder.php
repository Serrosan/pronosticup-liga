<?php

namespace Database\Seeders;

use App\Imports\LectorExcel;
use App\Models\Arbitro;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Estadio;
use App\Models\Temporada;
use App\Support\ConvierteFechasExcel;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class CalendarioPartidosSeeder extends Seeder
{
    use ConvierteFechasExcel;

    private array $mapaEquipos = [
        'CA Osasuna' => 'Club Atlético Osasuna',
        'Elche CF' => 'Elche Club de Fútbol',
        'FC Barcelona' => 'Fútbol Club Barcelona',
        'Getafe CF' => 'Getafe Club de Fútbol',
        'Levante UD' => 'Levante Unión Deportiva',
        'Málaga CF' => 'Málaga Club de Fútbol',
        'RC Celta de Vigo' => 'Real Club Celta de Vigo',
        'RC Deportivo La Coruña' => 'RC Deportivo de A Coruña',
        'Real Madrid CF' => 'Real Madrid Club de Fútbol',
        'Sevilla FC' => 'Sevilla Fútbol Club',
        'Valencia CF' => 'Valencia Club de Fútbol',
        'Villarreal CF' => 'Villarreal Club de Fútbol',
    ];

    public function run(): void
    {
        CalendarioPartido::query()->delete();

        $temporada = Temporada::first();

        $todasLasHojas = Excel::toArray(new LectorExcel, storage_path('app/imports/APP_Liga_2026.xlsx'));

        $filasEstadios = $todasLasHojas[2];
        array_shift($filasEstadios);

        $filasArbitros = $todasLasHojas[6];
        array_shift($filasArbitros);

        $filas = $todasLasHojas[5]; // CALENDARIO_PARTIDOS
        array_shift($filas);

        $creados = 0;
        $sinEquipo = 0;

        foreach ($filas as $fila) {
            if (empty($fila[2]) || empty($fila[4])) continue;

            $nombreLocal = $this->mapaEquipos[$fila[2]] ?? $fila[2];
            $nombreVisitante = $this->mapaEquipos[$fila[4]] ?? $fila[4];

            $equipoLocal = Equipo::where('nombre', $nombreLocal)->first();
            $equipoVisitante = Equipo::where('nombre', $nombreVisitante)->first();

            if (! $equipoLocal || ! $equipoVisitante) {
                $sinEquipo++;
                continue;
            }

            // id_estadio / id_arbitro son posicionales (1 = primera fila de esa pestaña)
            $estadio = null;
            if (! empty($fila[7])) {
                $filaEstadio = $filasEstadios[$fila[7] - 1] ?? null;
                if ($filaEstadio) {
                    $estadio = Estadio::where('nombre', $filaEstadio[1])->first();
                }
            }

            $arbitro = null;
            if (! empty($fila[12])) {
                $filaArbitro = $filasArbitros[$fila[12] - 1] ?? null;
                if ($filaArbitro) {
                    $arbitro = Arbitro::where('nombre', $filaArbitro[1])
                        ->where('apellidos', $filaArbitro[2])
                        ->first();
                }
            }

            CalendarioPartido::create([
                'id_temporada' => $temporada->id,
                'id_equipo_local' => $equipoLocal->id,
                'id_equipo_visitante' => $equipoVisitante->id,
                'id_estadio' => $estadio?->id,
                'jornada' => $this->numeroExcel($fila[11]),
                'horario_estimado' => $this->fechaHoraExcel($fila[5]),
                'horario_oficial' => $this->fechaHoraExcel($fila[6]),
                'id_arbitro' => $arbitro?->id,
                'goles_casa' => $this->numeroExcel($fila[8]),
                'goles_fuera' => $this->numeroExcel($fila[9]),
                'estado' => $fila[10],
                'asistencia' => $this->numeroExcel($fila[13]),
            ]);

            $creados++;
        }

        $this->command->info("Partidos importados: {$creados}");
        $this->command->info("Sin equipo encontrado: {$sinEquipo}");
    }
}