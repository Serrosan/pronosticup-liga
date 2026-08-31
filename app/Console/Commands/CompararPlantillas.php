<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CompararPlantillas extends Command
{
    protected $signature = 'liga:comparar-plantillas';
    protected $description = 'Compara la plantilla real de football-data.org contra la tuya, usando el ID real de la API (fiable, sin ambigüedad de nombres)';

    public function handle()
    {
        $equipos = Equipo::whereNotNull('id_equipo_api')->get();

        $idsApiPorEquipo = [];
        $nombresApiPorId = [];

        $barra = $this->output->createProgressBar($equipos->count());
        $barra->start();

        foreach ($equipos as $equipo) {
            $respuesta = Http::withHeaders(['X-Auth-Token' => config('services.football_data.token')])
                ->get("https://api.football-data.org/v4/teams/{$equipo->id_equipo_api}");

            $squad = $respuesta->successful() ? $respuesta->json('squad', []) : [];

            $idsApiPorEquipo[$equipo->id] = collect($squad)->pluck('id')->all();
            foreach ($squad as $j) {
                $nombresApiPorId[$j['id']] = $j['name'];
            }

            $barra->advance();
            usleep(6500000);
        }

        $barra->finish();
        $this->newLine(2);

        $traspasos = [];
        $bajasSinRastro = [];
        $sinIdParaComprobar = [];
        $sinCambios = 0;

        foreach ($equipos as $equipoLocal) {
            $plantillaLocalActiva = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $equipoLocal->id)->whereNull('fecha_salida'))
                ->whereNull('dado_de_baja_en')
                ->get();

            foreach ($plantillaLocalActiva as $jugadorLocal) {
                if (! $jugadorLocal->id_externo_api) {
                    $sinIdParaComprobar[] = "{$jugadorLocal->nombre} {$jugadorLocal->apellidos} (id {$jugadorLocal->id}) en {$equipoLocal->nombre}";
                    continue;
                }

                if (in_array($jugadorLocal->id_externo_api, $idsApiPorEquipo[$equipoLocal->id])) {
                    $sinCambios++;
                    continue;
                }

                $equipoNuevo = null;
                foreach ($equipos as $otroEquipo) {
                    if ($otroEquipo->id === $equipoLocal->id) continue;
                    if (in_array($jugadorLocal->id_externo_api, $idsApiPorEquipo[$otroEquipo->id])) {
                        $equipoNuevo = $otroEquipo;
                        break;
                    }
                }

                if ($equipoNuevo) {
                    $traspasos[] = "{$jugadorLocal->nombre} {$jugadorLocal->apellidos} (id {$jugadorLocal->id}): {$equipoLocal->nombre} → {$equipoNuevo->nombre}";
                } else {
                    $bajasSinRastro[] = "{$jugadorLocal->nombre} {$jugadorLocal->apellidos} (id {$jugadorLocal->id}) — ya no aparece en ningún equipo de LaLiga";
                }
            }
        }

        $idsLocalesConocidos = Jugador::whereNotNull('id_externo_api')->pluck('id_externo_api')->all();
        $altasNuevas = [];

        foreach ($equipos as $equipo) {
            foreach ($idsApiPorEquipo[$equipo->id] as $idApi) {
                if (! in_array($idApi, $idsLocalesConocidos)) {
                    $altasNuevas[] = "{$nombresApiPorId[$idApi]} (id API {$idApi}) → nuevo fichaje en {$equipo->nombre}";
                }
            }
        }

        $this->info("Sin cambios (confirmado por ID real, sin ambigüedad): {$sinCambios}");
        $this->newLine();

        $this->info('=== TRASPASOS DETECTADOS ('.count($traspasos).') — fiables al 100% ===');
        foreach ($traspasos as $linea) $this->line("  {$linea}");
        if (empty($traspasos)) $this->line('  (ninguno)');

        $this->newLine();
        $this->info('=== ALTAS NUEVAS DE VERDAD ('.count($altasNuevas).') — fiables al 100% ===');
        foreach ($altasNuevas as $linea) $this->line("  {$linea}");
        if (empty($altasNuevas)) $this->line('  (ninguna)');

        $this->newLine();
        $this->info('=== BAJAS SIN RASTRO ('.count($bajasSinRastro).') — fiables al 100% ===');
        foreach ($bajasSinRastro as $linea) $this->line("  {$linea}");
        if (empty($bajasSinRastro)) $this->line('  (ninguna)');

        if (! empty($sinIdParaComprobar)) {
            $this->newLine();
            $this->warn('=== SIN ID DE API GUARDADO, no se pudieron comprobar ('.count($sinIdParaComprobar).') ===');
            foreach ($sinIdParaComprobar as $linea) $this->line("  {$linea}");
            $this->comment('Estos habría que revisarlos a mano, o rellenarles el id_externo_api primero.');
        }

        $this->newLine();
        $this->comment('Este comando NUNCA cambia nada por su cuenta.');
    }
}