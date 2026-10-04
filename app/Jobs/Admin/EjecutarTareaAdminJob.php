<?php

namespace App\Jobs\Admin;

use App\Jobs\Partidos\SincronizarPartidoLaligaJob;
use App\Models\CalendarioPartido;
use App\Models\EjecucionTarea;
use App\Models\Temporada;
use App\Services\TareasAdmin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Ejecuta un lanzamiento manual pedido desde /admin/tareas y deja apuntado
 * cómo fue. El botón del admin solo crea la fila en "en_cola" y encola esto;
 * lo recoge la misma tarea de cola que ya lanza el programador cada minuto.
 */
class EjecutarTareaAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Un solo intento: relanzar por su cuenta una tarea que pulsó una persona
    // (y que quizá manda avisos) sería peor que dejarla marcada como fallida.
    public int $tries = 1;

    public int $timeout = 300;

    /** Pausa entre partidos al reimportar — la misma cortesía con LaLiga.com que el script. */
    private const PAUSA_SEGUNDOS = 3;

    public function __construct(public int $idEjecucion) {}

    public function handle(): void
    {
        $ejecucion = EjecucionTarea::find($this->idEjecucion);

        if (! $ejecucion || $ejecucion->estado !== 'en_cola') {
            return;
        }

        $ejecucion->update(['estado' => 'en_curso', 'iniciada_en' => now()]);

        try {
            [$ok, $salida] = $this->ejecutar($ejecucion);
        } catch (\Throwable $e) {
            [$ok, $salida] = [false, 'Error: '.$e->getMessage()];
        }

        $ejecucion->update([
            'estado' => $ok ? 'ok' : 'fallo',
            'salida' => TareasAdmin::recortar($salida) ?: null,
            'terminada_en' => now(),
        ]);
    }

    /** Si el proceso muere a medias (límite de tiempo, despliegue), que no se quede "en curso" para siempre. */
    public function failed(\Throwable $e): void
    {
        EjecucionTarea::where('id', $this->idEjecucion)
            ->whereIn('estado', ['en_cola', 'en_curso'])
            ->update(['estado' => 'fallo', 'salida' => 'No llegó a terminar: '.$e->getMessage(), 'terminada_en' => now()]);
    }

    /** @return array{0: bool, 1: string} [fue bien, salida] */
    private function ejecutar(EjecucionTarea $ejecucion): array
    {
        if (isset(TareasAdmin::PROGRAMADAS[$ejecucion->tarea])) {
            $codigo = Artisan::call(TareasAdmin::PROGRAMADAS[$ejecucion->tarea]['comando']);
            $salida = trim(Artisan::output());

            return [$codigo === 0, $salida !== '' ? $salida : ($codigo === 0 ? 'Terminó sin escribir nada.' : "Terminó con código de salida {$codigo}.")];
        }

        $idTemporada = Temporada::orderByDesc('fecha_inicio')->value('id');

        if ($ejecucion->tarea === TareasAdmin::REIMPORTAR_JORNADA) {
            $partidos = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
                ->where('id_temporada', $idTemporada)
                ->where('jornada', (int) ($ejecucion->parametros['jornada'] ?? 0))
                ->orderBy('horario_estimado')
                ->get();

            return $this->reimportar($partidos);
        }

        if ($ejecucion->tarea === TareasAdmin::REIMPORTAR_PARTIDO) {
            $partidos = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
                ->where('id', (int) ($ejecucion->parametros['id_partido'] ?? 0))
                ->get();

            return $this->reimportar($partidos);
        }

        return [false, 'Tarea desconocida.'];
    }

    /** @return array{0: bool, 1: string} */
    private function reimportar($partidos): array
    {
        if ($partidos->isEmpty()) {
            return [false, 'No hay partidos que reimportar.'];
        }

        $limite = now()->subMinutes(TareasAdmin::MINUTOS_PARTIDO_TERMINADO);
        $avisosAntes = $this->contarAvisos();
        $lineas = [];
        $importados = 0;
        $fallidos = 0;

        foreach ($partidos as $partido) {
            $etiqueta = "{$partido->equipoLocal->nombre_corto} - {$partido->equipoVisitante->nombre_corto} (J{$partido->jornada})";

            if (! $partido->horario_estimado || $partido->horario_estimado->gt($limite)) {
                $lineas[] = "– {$etiqueta}: aún no ha terminado, se salta.";
                continue;
            }

            if ($importados + $fallidos > 0) {
                sleep(self::PAUSA_SEGUNDOS);
            }

            try {
                // Se llama al trabajo directamente (no por la cola ni con sus 3
                // reintentos): si falla aquí, se apunta y se sigue con el siguiente,
                // sin mandar la notificación de "falló tras 3 intentos".
                app()->call([new SincronizarPartidoLaligaJob($partido->id), 'handle']);
                $importados++;
                $lineas[] = "✓ {$etiqueta}";
            } catch (\Throwable $e) {
                $fallidos++;
                $lineas[] = "✕ {$etiqueta}: {$e->getMessage()}";
            }
        }

        $avisosNuevos = max(0, $this->contarAvisos() - $avisosAntes);
        $lineas[] = '';
        $lineas[] = "{$importados} importado(s), {$fallidos} con error, {$avisosNuevos} aviso(s) nuevo(s) en «Avisos del scraper».";

        return [$fallidos === 0 && $importados > 0, implode(PHP_EOL, $lineas)];
    }

    /** Cuántas líneas de aviso hay ahora en el archivo del scraper (cada aviso empieza por "["). */
    private function contarAvisos(): int
    {
        $ruta = storage_path('app/scraper/avisos.log');

        if (! file_exists($ruta)) {
            return 0;
        }

        return preg_match_all('/^\[/m', (string) file_get_contents($ruta));
    }
}
