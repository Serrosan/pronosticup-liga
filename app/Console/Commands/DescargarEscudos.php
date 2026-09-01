<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DescargarEscudos extends Command
{
    protected $signature = 'liga:descargar-escudos';
    protected $description = 'Descarga los escudos reales desde football-data.org (siempre desde la API, nunca desde escudo_url local)';

    public function handle()
    {
        $equipos = Equipo::whereNotNull('id_equipo_api')->get();
        $descargados = 0;
        $fallidos = 0;

        foreach ($equipos as $equipo) {
            $respuesta = Http::withHeaders(['X-Auth-Token' => config('services.football_data.token')])
                ->get("https://api.football-data.org/v4/teams/{$equipo->id_equipo_api}");

            if (! $respuesta->successful() || ! $respuesta->json('crest')) {
                $this->warn("No se pudo obtener el escudo de {$equipo->nombre} desde la API");
                $fallidos++;
                usleep(6500000);
                continue;
            }

            $urlEscudo = $respuesta->json('crest');
            $respuestaImagen = Http::get($urlEscudo);

            if (! $respuestaImagen->successful()) {
                $this->warn("No se pudo descargar la imagen del escudo de {$equipo->nombre}");
                $fallidos++;
                usleep(6500000);
                continue;
            }

            $extension = Str::afterLast($urlEscudo, '.');
            $extension = in_array($extension, ['png', 'svg', 'jpg', 'jpeg']) ? $extension : 'png';
            $nombreArchivo = Str::slug($equipo->nombre).'-'.uniqid().'.'.$extension;

            Storage::disk('public')->put("equipos/{$nombreArchivo}", $respuestaImagen->body());
            $equipo->update(['escudo_url' => url(Storage::url("equipos/{$nombreArchivo}"))]);

            $this->info("✓ {$equipo->nombre}");
            $descargados++;
            usleep(6500000);
        }

        $this->info("Descargados: {$descargados}. Fallidos: {$fallidos}.");
    }
}