<?php

namespace App\Console\Commands;

use App\Models\Jugador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DescargarFotosJugadores extends Command
{
    protected $signature = 'liga:descargar-fotos-jugadores {--limite=}';
    protected $description = 'Busca en Wikidata/Wikimedia Commons la foto de cada jugador y la descarga';

    public function handle()
    {
        $jugadores = Jugador::whereNull('foto_url')->orderBy('id')->get();

        if ($limite = $this->option('limite')) {
            $jugadores = $jugadores->take((int) $limite);
        }

        $encontradas = 0;
        $sinFoto = 0;

        $barra = $this->output->createProgressBar($jugadores->count());
        $barra->start();

        foreach ($jugadores as $jugador) {
            usleep(700000);

            $nombreCompleto = trim("{$jugador->nombre} {$jugador->apellidos}");

            $respuestaBusqueda = Http::withHeaders(['User-Agent' => 'PronostiCupLiga/1.0 (proyecto personal)'])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbsearchentities',
                    'search' => $nombreCompleto,
                    'language' => 'es',
                    'format' => 'json',
                    'type' => 'item',
                    'limit' => 1,
                ]);

            $busqueda = $respuestaBusqueda->json();
            $qid = $busqueda['search'][0]['id'] ?? null;

            if (! $qid) {
                $this->line("  {$nombreCompleto}: sin entrada en Wikidata (HTTP {$respuestaBusqueda->status()})");
                $sinFoto++;
                $barra->advance();
                continue;
            }

            $entidad = Http::withHeaders(['User-Agent' => 'PronostiCupLiga/1.0 (proyecto personal)'])
                ->get('https://www.wikidata.org/w/api.php', [
                    'action' => 'wbgetentities',
                    'ids' => $qid,
                    'props' => 'claims',
                    'format' => 'json',
                ])->json();

            $nombreArchivoWiki = $entidad['entities'][$qid]['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? null;

            if (! $nombreArchivoWiki) {
                $this->line("  {$nombreCompleto}: entrada encontrada ({$qid}) pero sin foto");
                $sinFoto++;
                $barra->advance();
                continue;
            }

            $urlImagen = 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($nombreArchivoWiki).'?width=400';
            $respuestaImagen = Http::get($urlImagen);

            if (! $respuestaImagen->successful()) {
                $sinFoto++;
                $barra->advance();
                continue;
            }

            $extension = Str::afterLast($nombreArchivoWiki, '.') ?: 'jpg';
            $nombreArchivo = Str::slug($nombreCompleto).'-'.$jugador->id.'.'.$extension;

            Storage::disk('public')->put("jugadores/{$nombreArchivo}", $respuestaImagen->body());

            $jugador->update(['foto_url' => url(Storage::url("jugadores/{$nombreArchivo}"))]);

            $encontradas++;
            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);
        $this->info("Fotos encontradas: {$encontradas}. Sin foto disponible: {$sinFoto}.");
    }
}