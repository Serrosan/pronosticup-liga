<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CompletarIdsApi extends Command
{
    protected $signature = 'liga:completar-ids-api';
    protected $description = 'Intenta rellenar id_externo_api para jugadores que se quedaron sin él, buscando SOLO dentro de la plantilla real de su propio equipo';

    public function handle()
    {
        $equipos = Equipo::whereNotNull('id_equipo_api')->get();
        $completados = 0;
        $sinResolver = [];

        $barra = $this->output->createProgressBar($equipos->count());
        $barra->start();

        foreach ($equipos as $equipo) {
            $respuesta = Http::withHeaders(['X-Auth-Token' => config('services.football_data.token')])
                ->get("https://api.football-data.org/v4/teams/{$equipo->id_equipo_api}");

            $squad = $respuesta->successful() ? $respuesta->json('squad', []) : [];

            $idsYaUsados = Jugador::whereNotNull('id_externo_api')->pluck('id_externo_api')->all();
            $squadDisponible = collect($squad)->reject(fn ($j) => in_array($j['id'], $idsYaUsados))->values();

            $sinId = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $equipo->id)->whereNull('fecha_salida'))
                ->whereNull('dado_de_baja_en')
                ->whereNull('id_externo_api')
                ->get();

            foreach ($sinId as $jugadorLocal) {
                $normalizado = Str::of("{$jugadorLocal->nombre} {$jugadorLocal->apellidos}")->lower()->ascii()->toString();

                $mejorPorcentaje = 0;
                $mejorCandidato = null;

                foreach ($squadDisponible as $candidato) {
                    $normalizadoApi = Str::of($candidato['name'])->lower()->ascii()->toString();
                    similar_text($normalizado, $normalizadoApi, $porcentaje);
                    if ($porcentaje > $mejorPorcentaje) {
                        $mejorPorcentaje = $porcentaje;
                        $mejorCandidato = $candidato;
                    }
                }

                if ($mejorPorcentaje >= 55 && $mejorCandidato) {
                    $jugadorLocal->update(['id_externo_api' => $mejorCandidato['id']]);
                    $idsYaUsados[] = $mejorCandidato['id'];
                    $squadDisponible = $squadDisponible->reject(fn ($c) => $c['id'] === $mejorCandidato['id'])->values();
                    $completados++;
                } else {
                    $sinResolver[] = "{$jugadorLocal->nombre} {$jugadorLocal->apellidos} (id {$jugadorLocal->id}) en {$equipo->nombre} — candidatos API sin asignar: ".$squadDisponible->pluck('name')->implode(', ');
                }
            }

            $barra->advance();
            usleep(6500000);
        }

        $barra->finish();
        $this->newLine(2);

        $this->info("Completados: {$completados}");
        $this->newLine();

        if (! empty($sinResolver)) {
            $this->warn('=== SIN RESOLVER, revisar a mano ('.count($sinResolver).') ===');
            foreach ($sinResolver as $linea) {
                $this->line("  {$linea}");
            }
        }
    }
}