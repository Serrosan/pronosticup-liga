<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepararJornadaScrapeada extends Command
{
    protected $signature = 'liga:preparar-jornada';

    protected $description = 'Traduce los .html descargados por scripts/sincronizar_jornada.sh al JSON que espera liga:importar-partido-detalle';

    private const CARPETA_ENTRADA = 'scraper/jornada';
    private const RUTA_SALIDA = 'scraper/jornada.json';

    public function handle(): int
    {
        $carpeta = storage_path('app/'.self::CARPETA_ENTRADA);
        $archivos = glob("{$carpeta}/*.html");

        if (empty($archivos)) {
            $this->error("No hay ningún .html en storage/app/".self::CARPETA_ENTRADA." — ejecuta primero scripts/sincronizar_jornada.sh");
            return self::FAILURE;
        }

        $partidos = [];
        $fallos = [];

        foreach ($archivos as $ruta) {
            $html = file_get_contents($ruta);

            if (! preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
                $fallos[] = basename($ruta).' — no se encontró __NEXT_DATA__ (¿página de error de LaLiga?)';
                continue;
            }

            $data = json_decode($m[1], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fallos[] = basename($ruta).' — JSON inválido: '.json_last_error_msg();
                continue;
            }

            $match = $data['props']['pageProps']['match'] ?? null;
            $pageData = $data['props']['pageProps']['data'] ?? null;
            $events = $data['props']['pageProps']['events'] ?? null;

            if (! $match || ! $pageData || $events === null) {
                $fallos[] = basename($ruta).' — faltan claves esperadas (match/data/events)';
                continue;
            }

            $partidos[] = [
                'equipo_local' => $match['home_team']['nickname'],
                'equipo_visitante' => $match['away_team']['nickname'],
                'id_laliga_local' => $match['home_team']['id'],
                'id_laliga_visitante' => $match['away_team']['id'],
                'jornada' => $match['gameweek']['week'],
                'formacion_local' => (string) ($match['home_formation'] ?? ''),
                'formacion_visitante' => (string) ($match['away_formation'] ?? ''),
                'lineups' => $pageData['lineups'] ?? null,
                'stats' => $pageData['stats'] ?? null,
                'events' => $events,
            ];
        }

        $rutaSalida = storage_path('app/'.self::RUTA_SALIDA);
        file_put_contents($rutaSalida, json_encode($partidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info(count($partidos).' partido(s) traducidos:');
        foreach ($partidos as $p) {
            $this->line("  - {$p['equipo_local']} vs {$p['equipo_visitante']} (J{$p['jornada']}) — ".count($p['events']).' eventos');
        }

        if ($fallos) {
            $this->newLine();
            $this->warn(count($fallos).' fallo(s):');
            foreach ($fallos as $f) {
                $this->line("  ⚠️  {$f}");
            }
        }

        $this->newLine();
        $this->info("Guardado en: storage/app/".self::RUTA_SALIDA);

        return self::SUCCESS;
    }
}