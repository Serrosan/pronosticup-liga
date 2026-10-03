<?php

namespace App\Jobs\Partidos;

use App\Models\CalendarioPartido;
use App\Models\Temporada;
use App\Models\User;
use App\Notifications\SincronizacionFallida;
use App\Services\ImportadorPartidoDetalle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Trae alineaciones/estadísticas/eventos de UN partido desde LaLiga.com.
 * Pensada para dispararse una única vez, justo cuando ese partido concreto
 * pasa a "Jugado" — nunca por su cuenta, nunca en bucle.
 */
class SincronizarPartidoLaligaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 3 intentos, 15 minutos entre cada uno — por si LaLiga tarda en publicar
    // los datos completos justo tras el pitido, o está caída un momento.
    public int $tries = 3;

    public function __construct(public int $idPartido) {}

    public function backoff(): array
    {
        return [900, 900]; // 15 min antes del 2º intento, 15 min antes del 3º
    }

    public function handle(ImportadorPartidoDetalle $importador): void
    {
        $partido = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->findOrFail($this->idPartido);

        $urlResultados = "https://www.laliga.com/laliga-easports/resultados/2026-27/jornada-{$partido->jornada}";
        $html = $this->descargar($urlResultados);

        preg_match_all('/href="(\/partido\/temporada-[^"]+)"/', $html, $m);
        $links = array_unique($m[1]);

        $link = $this->encontrarLinkDelPartido($links, $partido->equipoLocal->nombre, $partido->equipoVisitante->nombre);

        if (! $link) {
            throw new \RuntimeException("No encontré el link de {$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre} en {$urlResultados}");
        }

        $htmlPartido = $this->descargar("https://www.laliga.com{$link}");

        if (! preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $htmlPartido, $m)) {
            throw new \RuntimeException("No se encontró __NEXT_DATA__ en la página de {$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre}.");
        }

        $data = json_decode($m[1], true);
        $match = $data['props']['pageProps']['match'] ?? null;
        $pageData = $data['props']['pageProps']['data'] ?? null;
        $events = $data['props']['pageProps']['events'] ?? null;

        if (! $match || ! $pageData || $events === null) {
            throw new \RuntimeException("Faltan claves esperadas (match/data/events) para {$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre}.");
        }

        $partidoTraducido = [
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

        $idTemporada = Temporada::orderByDesc('fecha_inicio')->value('id');
        $resultado = $importador->importar($partidoTraducido, $idTemporada);

        if (! $resultado['ok']) {
            throw new \RuntimeException('Importación fallida para '.$partido->equipoLocal->nombre.' vs '.$partido->equipoVisitante->nombre.': '.implode('; ', $resultado['avisos']));
        }

        // Los avisos normales (jugador sin emparejar, colisión...) no son un
        // fallo del job, pero sí queremos poder revisarlos luego — mismo
        // archivo y formato que usa el flujo manual de jornada completa.
        if ($resultado['avisos']) {
            $rutaLog = storage_path('app/scraper/avisos.log');
            if (! is_dir(dirname($rutaLog))) {
                mkdir(dirname($rutaLog), 0755, true);
            }
            $etiqueta = "{$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre} (J{$partido->jornada})";
            $cabecera = '=== '.now()->format('Y-m-d H:i:s')." — auto, {$etiqueta} ==="; // guion automático, para distinguirlo del manual
            $lineas = collect($resultado['avisos'])->map(fn ($a) => "[{$etiqueta}] {$a}")->implode(PHP_EOL);
            file_put_contents($rutaLog, $cabecera.PHP_EOL.$lineas.PHP_EOL.PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Se llama automáticamente cuando se agotan los 3 intentos. Mismo patrón
     * que ya usa SincronizarPartidosJob para avisar de fallos de verdad.
     */
    public function failed(\Throwable $e): void
    {
        User::where('es_superadmin', true)->get()->each(function ($admin) use ($e) {
            $admin->notify(new SincronizacionFallida(
                "Scraping de LaLiga.com falló tras 3 intentos para el partido id {$this->idPartido}: {$e->getMessage()}"
            ));
        });
    }

    private function descargar(string $url): string
    {
        $contexto = stream_context_create(['http' => [
            'header' => 'User-Agent: Mozilla/5.0 (compatible; PronostiCupBot/1.0; uso personal, no comercial)',
            'timeout' => 15,
        ]]);
        return file_get_contents($url, false, $contexto) ?: '';
    }

    private function encontrarLinkDelPartido(array $links, string $nombreLocal, string $nombreVisitante): ?string
    {
        $normalizar = fn ($t) => Str::of($t)->lower()->ascii()->toString();
        $conectores = ['de', 'del', 'la', 'los', 'las', 'club', 'cf', 'fc', 'ud', 'rc', 'ca', 'sd', 'cd', 'sad'];

        $palabrasSignificativas = function (string $nombre) use ($normalizar, $conectores) {
            return collect(explode(' ', $normalizar($nombre)))
                ->filter(fn ($p) => strlen($p) >= 4 && ! in_array($p, $conectores, true))
                ->values()
                ->all();
        };

        $palabrasLocal = $palabrasSignificativas($nombreLocal);
        $palabrasVisitante = $palabrasSignificativas($nombreVisitante);

        foreach ($links as $link) {
            $slug = $normalizar($link);
            $tieneLocal = collect($palabrasLocal)->contains(fn ($p) => str_contains($slug, $p));
            $tieneVisitante = collect($palabrasVisitante)->contains(fn ($p) => str_contains($slug, $p));
            if ($tieneLocal && $tieneVisitante) {
                return $link;
            }
        }

        return null;
    }
}