<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\GoleadorJornada;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Pronostico;
use Illuminate\Console\Command;

class SimularJornada extends Command
{
    protected $signature = 'liga:simular-jornada {jornada} {--liga=}';
    protected $description = 'Rellena una jornada con resultados y pronósticos de prueba realistas, sin cerrarla (usa el botón real de admin para eso)';

    public function handle()
    {
        $numeroJornada = (int) $this->argument('jornada');

        $liga = $this->option('liga')
            ? Liga::findOrFail($this->option('liga'))
            : Liga::first();

        if (! $liga) {
            $this->error('No hay ninguna liga en la base de datos.');
            return 1;
        }

        $this->info("Simulando jornada {$numeroJornada} para la liga \"{$liga->nombre}\"...");

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $numeroJornada)
            ->get();

        if ($partidos->isEmpty()) {
            $this->error("No hay partidos para la jornada {$numeroJornada} en esta temporada.");
            return 1;
        }

        $partidosPendientes = $partidos->where('estado', 'Programado');

        foreach ($partidosPendientes as $partido) {
            $golesCasa = rand(0, 3);
            $golesFuera = rand(0, 3);

            $partido->update([
                'estado' => 'Jugado',
                'goles_casa' => $golesCasa,
                'goles_fuera' => $golesFuera,
            ]);

            $this->crearGolesDeEquipo($partido, $partido->id_equipo_local, $golesCasa);
            $this->crearGolesDeEquipo($partido, $partido->id_equipo_visitante, $golesFuera);
        }

        $this->info("  {$partidosPendientes->count()} partido(s) marcados como Jugado con resultado aleatorio.");

        $usuarios = $liga->usuarios;
        $pronosticosCreados = 0;

        foreach ($usuarios as $usuario) {
            foreach ($partidos as $partido) {
                $partido->refresh();

                $yaExiste = Pronostico::where('id_usuario', $usuario->id)
                    ->where('id_liga', $liga->id)
                    ->where('id_partido', $partido->id)
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                [$golesLocal, $golesVisitante] = $this->generarPronosticoRealista($partido->goles_casa, $partido->goles_fuera);

                $resultado1x2 = $this->calcularResultado($golesLocal, $golesVisitante);

                Pronostico::create([
                    'id_usuario' => $usuario->id,
                    'id_liga' => $liga->id,
                    'id_partido' => $partido->id,
                    'resultado_1x2' => $resultado1x2,
                    'goles_local_predicho' => $golesLocal,
                    'goles_visitante_predicho' => $golesVisitante,
                    'enviado_en' => now(),
                ]);

                $pronosticosCreados++;
            }
        }

        $this->info("  {$pronosticosCreados} pronóstico(s) de prueba creados (variedad de aciertos: exacto/diferencia/signo/fallo).");

        $golesadoresCreados = 0;

        foreach ($usuarios as $usuario) {
            $yaTiene = GoleadorJornada::where('id_usuario', $usuario->id)
                ->where('id_liga', $liga->id)
                ->where('jornada', $numeroJornada)
                ->exists();

            if ($yaTiene) {
                continue;
            }

            $noRepetibles = GoleadorJornada::where('id_usuario', $usuario->id)
                ->where('id_liga', $liga->id)
                ->where('jornada', $numeroJornada - 1)
                ->pluck('id_jugador');

            $jugadoresElegidos = Jugador::whereNull('dado_de_baja_en')
                ->whereNotIn('id', $noRepetibles)
                ->inRandomOrder()
                ->limit(5)
                ->get();

            if ($jugadoresElegidos->count() < 5) {
                $this->warn("  No hay suficientes jugadores disponibles para {$usuario->nombre}, se omite.");
                continue;
            }

            foreach ($jugadoresElegidos as $jugador) {
                GoleadorJornada::create([
                    'id_usuario' => $usuario->id,
                    'id_liga' => $liga->id,
                    'jornada' => $numeroJornada,
                    'id_jugador' => $jugador->id,
                ]);
            }

            $golesadoresCreados++;
        }

        $this->info("  Goleadores elegidos para {$golesadoresCreados} usuario(s).");
        $this->newLine();
        $this->info('Listo. Ahora ve al admin y cierra la jornada '.$numeroJornada.' de la forma normal, para probar el flujo real.');

        return 0;
    }

    private function generarPronosticoRealista(int $golesCasaReal, int $golesFueraReal): array
    {
        $tirada = rand(1, 100);

        if ($tirada <= 20) {
            return [$golesCasaReal, $golesFueraReal];
        }

        if ($tirada <= 45) {
            return [$golesCasaReal + 1, $golesFueraReal + 1];
        }

        if ($tirada <= 70) {
            $resultadoReal = $this->calcularResultado($golesCasaReal, $golesFueraReal);
            return match ($resultadoReal) {
                'Local' => [rand(2, 3), rand(0, 1)],
                'Visitante' => [rand(0, 1), rand(2, 3)],
                default => [rand(1, 2), rand(1, 2)],
            };
        }

        return [rand(0, 3), rand(0, 3)];
    }

    private function calcularResultado(int $golesCasa, int $golesFuera): string
    {
        if ($golesCasa > $golesFuera) return 'Local';
        if ($golesCasa < $golesFuera) return 'Visitante';
        return 'Empate';
    }

    private function crearGolesDeEquipo($partido, int $idEquipo, int $totalGoles): void
    {
        if ($totalGoles === 0) {
            return;
        }

        $jugadoresDelEquipo = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $idEquipo)->whereNull('fecha_salida'))
            ->whereNull('dado_de_baja_en')
            ->inRandomOrder()
            ->limit($totalGoles)
            ->get();

        foreach ($jugadoresDelEquipo as $index => $jugador) {
            \App\Models\EventoPartido::create([
                'id_partido' => $partido->id,
                'id_jugador' => $jugador->id,
                'id_equipo' => $idEquipo,
                'minuto' => rand(1, 90),
                'tipo_evento' => 'gol',
            ]);
        }
    }
}