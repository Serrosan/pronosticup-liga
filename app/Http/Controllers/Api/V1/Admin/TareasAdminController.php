<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\EjecutarTareaAdminJob;
use App\Models\CalendarioPartido;
use App\Models\EjecucionTarea;
use App\Models\Temporada;
use App\Services\TareasAdmin;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * /admin/tareas — ver cuándo corrió cada tarea programada, lanzarla a mano y
 * reimportar partidos de LaLiga.com sin entrar por terminal.
 *
 * Lanzar no ejecuta nada en esta petición: deja el encargo en la cola y lo
 * recoge el programador en menos de un minuto (una reimportación tarda más de
 * lo que aguanta una petición web).
 */
class TareasAdminController extends Controller
{
    private const ACTIVOS = ['en_cola', 'en_curso'];

    public function index()
    {
        $this->darPorPerdidosLosAtascados();

        $programadas = EjecucionTarea::where('origen', 'programada')->get()->keyBy('tarea');
        $manuales = EjecucionTarea::where('origen', 'manual')->orderByDesc('id')->limit(200)->get();
        $descripciones = $this->descripcionesDeComandos();

        $tareas = [];
        foreach (TareasAdmin::PROGRAMADAS as $clave => $tarea) {
            $ultimaManual = $manuales->firstWhere('tarea', $clave);

            $tareas[] = [
                'clave' => $clave,
                'nombre' => $tarea['nombre'],
                'comando' => $tarea['comando'],
                'descripcion' => $descripciones[$tarea['comando']] ?? null,
                'frecuencia' => $tarea['frecuencia'],
                'proxima' => $this->proximaEjecucion($tarea['cron']),
                'confirmar' => $tarea['confirmar'],
                'ultima_programada' => $this->fila($programadas->get($clave)),
                'ultima_manual' => $this->fila($ultimaManual),
                'activa' => $ultimaManual !== null && in_array($ultimaManual->estado, self::ACTIVOS, true),
            ];
        }

        $herramientas = [];
        foreach (TareasAdmin::MANUALES as $clave => $tarea) {
            $ultimaManual = $manuales->firstWhere('tarea', $clave);

            $herramientas[] = [
                'clave' => $clave,
                'nombre' => $tarea['nombre'],
                'comando' => $tarea['comando'],
                'descripcion' => $descripciones[$tarea['comando']] ?? null,
                'frecuencia' => null,
                'proxima' => null,
                'confirmar' => $tarea['confirmar'],
                'ultima_programada' => null,
                'ultima_manual' => $this->fila($ultimaManual),
                'activa' => $ultimaManual !== null && in_array($ultimaManual->estado, self::ACTIVOS, true),
            ];
        }

        $reimportacionActiva = $manuales->first(
            fn ($e) => TareasAdmin::esReimportacion($e->tarea) && in_array($e->estado, self::ACTIVOS, true)
        );

        $ultimaSenal = $programadas->max('updated_at');

        return response()->json(['data' => [
            'tareas' => $tareas,
            'herramientas' => $herramientas,
            'programador' => [
                'ultima_senal' => $ultimaSenal?->toIso8601String(),
                // La sincronización corre cada 2 minutos: 6 sin noticias es que algo va mal.
                'parado' => $ultimaSenal === null || $ultimaSenal->lt(now()->subMinutes(6)),
            ],
            'cola' => $this->estadoDeLaCola(),
            'scraper' => [
                'total_jornadas' => TareasAdmin::TOTAL_JORNADAS,
                'jornada_sugerida' => $this->jornadaSugerida(),
                'activa' => $this->fila($reimportacionActiva),
            ],
            'historial' => $manuales->take(20)->map(fn ($e) => $this->fila($e))->values(),
            'hay_activas' => $manuales->contains(fn ($e) => in_array($e->estado, self::ACTIVOS, true)),
        ]]);
    }

    /** Partidos de una jornada, para poder reimportar uno suelto. */
    public function partidos(int $jornada)
    {
        $limite = now()->subMinutes(TareasAdmin::MINUTOS_PARTIDO_TERMINADO);

        $partidos = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
            ->where('id_temporada', $this->idTemporada())
            ->where('jornada', $jornada)
            ->orderBy('horario_estimado')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                'visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                'horario' => $p->horario_estimado?->toIso8601String(),
                'terminado' => $p->horario_estimado !== null && $p->horario_estimado->lte($limite),
            ]);

        return response()->json(['data' => $partidos]);
    }

    public function lanzar(Request $request, string $clave)
    {
        $parametros = null;

        if (TareasAdmin::comando($clave) !== null) {
            // Sin parámetros: se lanza el comando del catálogo tal cual.
        } elseif ($clave === TareasAdmin::REIMPORTAR_JORNADA) {
            $datos = $request->validate(['jornada' => ['required', 'integer', 'min:1', 'max:'.TareasAdmin::TOTAL_JORNADAS]]);
            $parametros = ['jornada' => (int) $datos['jornada'], 'etiqueta' => "Jornada {$datos['jornada']}"];
        } elseif ($clave === TareasAdmin::REIMPORTAR_PARTIDO) {
            $datos = $request->validate(['id_partido' => ['required', 'integer']]);
            $partido = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->findOrFail((int) $datos['id_partido']);
            $parametros = [
                'id_partido' => $partido->id,
                'etiqueta' => ($partido->equipoLocal->nombre_corto ?? '?').' - '.($partido->equipoVisitante->nombre_corto ?? '?')." (J{$partido->jornada})",
            ];
        } elseif ($clave === TareasAdmin::REIMPORTAR_CAMBIOS) {
            $parametros = ['etiqueta' => 'Partidos con cambios de plantilla'];
        } else {
            return response()->json(['message' => 'Esa tarea no existe.'], 404);
        }

        // Una sola a la vez por tarea. Las dos reimportaciones cuentan como la
        // misma: nunca dos descargas de LaLiga.com en paralelo.
        $grupo = TareasAdmin::esReimportacion($clave)
            ? [TareasAdmin::REIMPORTAR_JORNADA, TareasAdmin::REIMPORTAR_PARTIDO]
            : [$clave];

        $yaHayUna = EjecucionTarea::where('origen', 'manual')
            ->whereIn('tarea', $grupo)
            ->whereIn('estado', self::ACTIVOS)
            ->where('created_at', '>', now()->subMinutes(TareasAdmin::MINUTOS_LANZAMIENTO_PERDIDO))
            ->exists();

        if ($yaHayUna) {
            return response()->json(['message' => 'Ya hay un lanzamiento de esta tarea en marcha. Espera a que termine.'], 409);
        }

        $usuario = $request->user();
        $atributos = $usuario?->getAttributes() ?? [];

        $ejecucion = EjecucionTarea::create([
            'tarea' => $clave,
            'origen' => 'manual',
            'estado' => 'en_cola',
            'id_usuario' => $usuario?->id,
            'lanzada_por' => $atributos['nombre'] ?? $atributos['name'] ?? ($usuario ? "usuario {$usuario->id}" : null),
            'parametros' => $parametros,
        ]);

        EjecutarTareaAdminJob::dispatch($ejecucion->id);

        return response()->json(['data' => $this->fila($ejecucion), 'message' => 'Encargada. Empezará en menos de un minuto.'], 201);
    }

    /** Un lanzamiento que lleva demasiado sin terminar (lo cortó un despliegue, p. ej.) se marca como fallido. */
    private function darPorPerdidosLosAtascados(): void
    {
        EjecucionTarea::where('origen', 'manual')
            ->whereIn('estado', self::ACTIVOS)
            ->where('created_at', '<=', now()->subMinutes(TareasAdmin::MINUTOS_LANZAMIENTO_PERDIDO))
            ->update([
                'estado' => 'fallo',
                'salida' => 'No llegó a terminar (¿un despliegue a medias, o el programador parado?).',
                'terminada_en' => now(),
            ]);
    }

    private function fila(?EjecucionTarea $e): ?array
    {
        if (! $e) {
            return null;
        }

        return [
            'id' => $e->id,
            'tarea' => $e->tarea,
            'nombre' => $this->nombreDeTarea($e->tarea),
            'origen' => $e->origen,
            'estado' => $e->estado,
            'lanzada_por' => $e->lanzada_por,
            'etiqueta' => $e->parametros['etiqueta'] ?? null,
            'salida' => $e->salida,
            'encargada_en' => $e->created_at?->toIso8601String(),
            'iniciada_en' => $e->iniciada_en?->toIso8601String(),
            'terminada_en' => $e->terminada_en?->toIso8601String(),
            'duracion_segundos' => $e->iniciada_en && $e->terminada_en
                ? round(abs($e->terminada_en->diffInMilliseconds($e->iniciada_en)) / 1000, 1)
                : null,
        ];
    }

    private function nombreDeTarea(string $clave): string
    {
        return match (true) {
            isset(TareasAdmin::PROGRAMADAS[$clave]) => TareasAdmin::PROGRAMADAS[$clave]['nombre'],
            isset(TareasAdmin::MANUALES[$clave]) => TareasAdmin::MANUALES[$clave]['nombre'],
            $clave === TareasAdmin::REIMPORTAR_JORNADA => 'Reimportar jornada de LaLiga',
            $clave === TareasAdmin::REIMPORTAR_PARTIDO => 'Reimportar partido de LaLiga',
            $clave === TareasAdmin::REIMPORTAR_CAMBIOS => 'Aplicar cambios de plantilla a los partidos',
            default => $clave,
        };
    }

    private function proximaEjecucion(string $cron): ?string
    {
        try {
            return Carbon::instance((new CronExpression($cron))->getNextRunDate(now()))->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** La descripción que cada comando declara de sí mismo — así el panel nunca dice otra cosa que el código. */
    private function descripcionesDeComandos(): array
    {
        try {
            $comandos = Artisan::all();
        } catch (\Throwable $e) {
            return [];
        }

        $descripciones = [];
        foreach (array_merge(TareasAdmin::PROGRAMADAS, TareasAdmin::MANUALES) as $tarea) {
            if (isset($comandos[$tarea['comando']])) {
                $descripciones[$tarea['comando']] = $comandos[$tarea['comando']]->getDescription();
            }
        }

        return $descripciones;
    }

    private function estadoDeLaCola(): array
    {
        try {
            return ['pendientes' => DB::table('jobs')->count(), 'fallidos' => DB::table('failed_jobs')->count()];
        } catch (\Throwable $e) {
            return ['pendientes' => null, 'fallidos' => null];
        }
    }

    private function idTemporada(): ?int
    {
        return Temporada::orderByDesc('fecha_inicio')->value('id');
    }

    /** La última jornada que ya tiene algún partido empezado. */
    private function jornadaSugerida(): int
    {
        $jornada = CalendarioPartido::where('id_temporada', $this->idTemporada())
            ->where('horario_estimado', '<=', now())
            ->max('jornada');

        return (int) ($jornada ?: 1);
    }
}
