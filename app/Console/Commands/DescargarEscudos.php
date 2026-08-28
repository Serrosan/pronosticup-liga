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
    protected $description = 'Descarga los escudos reales de los equipos y los guarda en el propio servidor';

    public function handle()
    {
        $equipos = Equipo::whereNotNull('escudo_url')->get();
        $descargados = 0;
        $fallidos = 0;

        foreach ($equipos as $equipo) {
            $respuesta = Http::get($equipo->escudo_url);

            if (! $respuesta->successful()) {
                $this->warn("No se pudo descargar el escudo de {$equipo->nombre} ({$equipo->escudo_url})");
                $fallidos++;
                continue;
            }

            $extension = Str::afterLast($equipo->escudo_url, '.');
            $extension = in_array($extension, ['png', 'svg', 'jpg', 'jpeg']) ? $extension : 'png';
            $nombreArchivo = Str::slug($equipo->nombre).'.'.$extension;

            Storage::disk('public')->put("equipos/{$nombreArchivo}", $respuesta->body());

            $equipo->update(['escudo_url' => url(Storage::url("equipos/{$nombreArchivo}"))]);

            $this->info("✓ {$equipo->nombre}");
            $descargados++;
        }

        $this->info("Descargados: {$descargados}. Fallidos: {$fallidos}.");
    }
}