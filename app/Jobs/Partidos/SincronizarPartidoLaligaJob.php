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
use Illuminate\Support\Facades\Log;
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

    /**
     * Si un intento falla y aún quedan reintentos, se apunta como AVISO (no error)
     * y se vuelve a la cola en 15 minutos: LaLiga a veces tarda en publicar la
     * página o falla un momento, y casi siempre se arregla solo. Solo el último
     * intento fallido cuenta como error (y avisa al admin vía failed()).
     * Lanzado a mano (cola síncrona) no hay reintentos: el error sale tal cual.
     */
    public function handle(ImportadorPartidoDetalle $importador): void
    {
        try {
            $this->importarPartido($importador);
        } catch (\Throwable $e) {
            $enCola = $this->job && $this->job->getConnectionName() !== 'sync';

            if ($enCola && $this->attempts() < $this->tries) {
                Log::warning("Scraper de LaLiga: intento {$this->attempts()} de {$this->tries} fallido para el partido {$this->idPartido}, se reintenta en 15 minutos. {$e->getMessage()}");
                $this->release(900);

                return;
            }

            throw $e;
        }
    }

    private function importarPartido(ImportadorPartidoDetalle $importador): void
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

        // Un partido terminado siempre trae eventos (como mínimo, los cambios).
        // Si llega vacío es que LaLiga aún no los ha publicado: importar ahora
        // BORRARÍA los eventos que ya hubiera guardados y no pondría ninguno.
        // Mejor fallar — el automático lo reintenta a los 15 minutos.
        if (count($events) === 0) {
            throw new \RuntimeException("LaLiga aún no ha publicado los eventos de {$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre} — no se importa nada.");
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

        // Red de seguridad: si el enlace elegido era de OTRO partido, el importador habrá
        // guardado ese otro (con sus propios datos, no se estropea nada) y este seguiría
        // vacío. Nunca darlo por bueno: fallar con un error claro.
        if ((int) ($resultado['id_partido'] ?? 0) !== $this->idPartido) {
            throw new \RuntimeException("El enlace de LaLiga elegido era de otro partido (id {$resultado['id_partido']}), no de {$partido->equipoLocal->nombre} vs {$partido->equipoVisitante->nombre}: este no se ha importado.");
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
            'ignore_errors' => true, // para poder leer el código de respuesta aunque no sea 200
        ]]);

        $html = @file_get_contents($url, false, $contexto);

        // Código de la última respuesta (tras posibles redirecciones).
        $cabeceras = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : [];
        $estado = null;
        foreach ($cabeceras as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linea, $m)) {
                $estado = (int) $m[1];
            }
        }

        if ($html === false || ($estado !== null && $estado !== 200)) {
            throw new \RuntimeException('LaLiga respondió '.($estado ?? 'sin respuesta')." al pedir {$url}");
        }

        return $html;
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

        // Las palabras que comparten los dos equipos ("real", "club", "fútbol"…) no sirven
        // para distinguir: con ellas, "Real Club Celta" vs "Real Betis" casaba con el
        // enlace del Barça - Real Madrid. Primero se busca solo con las que distinguen y
        // gana el enlace con más coincidencias; si así no sale ninguno, se usan todas.
        $comunes = array_intersect($palabrasLocal, $palabrasVisitante);
        $pasadas = [
            [array_values(array_diff($palabrasLocal, $comunes)) ?: $palabrasLocal, array_values(array_diff($palabrasVisitante, $comunes)) ?: $palabrasVisitante],
            [$palabrasLocal, $palabrasVisitante],
        ];

        foreach ($pasadas as [$buscarLocal, $buscarVisitante]) {
            $mejor = null;
            $mejorPuntos = 0;

            foreach ($links as $link) {
                $slug = $normalizar($link);
                $local = collect($buscarLocal)->sum(fn ($p) => substr_count($slug, $p));
                $visitante = collect($buscarVisitante)->sum(fn ($p) => substr_count($slug, $p));

                if ($local > 0 && $visitante > 0 && $local + $visitante > $mejorPuntos) {
                    $mejor = $link;
                    $mejorPuntos = $local + $visitante;
                }
            }

            if ($mejor) {
                return $mejor;
            }
        }

        return null;
    }
}
