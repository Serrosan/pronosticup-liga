<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AsignarIdsManual extends Command
{
    protected $signature = 'liga:asignar-ids-manual';
    protected $description = 'Revisión interactiva, uno a uno, de los jugadores que no se pudieron emparejar automáticamente';

    public function handle()
    {
        $equipos = Equipo::whereNotNull('id_equipo_api')->get();
        $asignados = 0;
        $saltados = 0;

        foreach ($equipos as $equipo) {
            $sinId = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $equipo->id)->whereNull('fecha_salida'))
                ->whereNull('dado_de_baja_en')
                ->whereNull('id_externo_api')
                ->get();

            if ($sinId->isEmpty()) {
                continue;
            }

            $respuesta = Http::withHeaders(['X-Auth-Token' => config('services.football_data.token')])
                ->get("https://api.football-data.org/v4/teams/{$equipo->id_equipo_api}");

            $squad = $respuesta->successful() ? $respuesta->json('squad', []) : [];

            $idsYaUsados = Jugador::whereNotNull('id_externo_api')->pluck('id_externo_api')->all();
            $squadDisponible = collect($squad)->reject(fn ($j) => in_array($j['id'], $idsYaUsados))->values();

            foreach ($sinId as $jugadorLocal) {
                if ($squadDisponible->isEmpty()) {
                    $this->warn("Sin más candidatos disponibles en {$equipo->nombre} para {$jugadorLocal->nombre} {$jugadorLocal->apellidos}, saltando.");
                    $saltados++;
                    continue;
                }

                $this->newLine();
                $this->info("Equipo: {$equipo->nombre}");
                $this->line("Jugador local: <fg=yellow>{$jugadorLocal->nombre} {$jugadorLocal->apellidos}</> (posición: {$jugadorLocal->posicion})");
                $this->line('Candidatos de la API:');

                foreach ($squadDisponible as $i => $candidato) {
                    $this->line("  [{$i}] {$candidato['name']} ({$candidato['position']})");
                }

                $eleccion = $this->ask('¿Cuál es? (número, o "s" para saltar)');

                if ($eleccion === 's' || $eleccion === '') {
                    $saltados++;
                    continue;
                }

                if (is_numeric($eleccion) && isset($squadDisponible[(int) $eleccion])) {
                    $elegido = $squadDisponible[(int) $eleccion];
                    $jugadorLocal->update(['id_externo_api' => $elegido['id']]);
                    $squadDisponible = $squadDisponible->reject(fn ($c) => $c['id'] === $elegido['id'])->values();
                    $asignados++;
                    $this->info("✓ Asignado: {$elegido['name']}");
                } else {
                    $this->error('Opción no válida, saltando este jugador.');
                    $saltados++;
                }
            }
        }

        $this->newLine();
        $this->info("Terminado. Asignados: {$asignados}. Saltados: {$saltados}.");
    }
}