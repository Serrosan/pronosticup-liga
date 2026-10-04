<?php

namespace App\Services;

use App\Models\EjecucionTarea;

/**
 * Catálogo CERRADO de lo que se puede ver y lanzar desde /admin/tareas.
 *
 * Es la única fuente de las tareas programadas: routes/console.php las
 * programa leyendo esta lista, y el panel las enseña leyendo la misma. Para
 * añadir una tarea nueva al panel basta con añadirla aquí.
 *
 * Nunca se ejecuta nada que no esté en esta lista: el panel no acepta
 * comandos escritos a mano.
 */
class TareasAdmin
{
    public const PROGRAMADAS = [
        'sincronizar-partidos' => [
            'comando' => 'liga:sincronizar-partidos',
            'nombre' => 'Sincronizar partidos',
            'cron' => '*/2 * * * *',
            'frecuencia' => 'Cada 2 minutos',
            'confirmar' => null,
        ],
        'avisar-pendientes' => [
            'comando' => 'liga:avisar-pendientes',
            'nombre' => 'Avisar pendientes',
            'cron' => '*/30 * * * *',
            'frecuencia' => 'Cada 30 minutos',
            'confirmar' => 'Esta tarea puede enviar avisos reales a los usuarios. ¿Lanzarla ahora?',
        ],
        'actualizar-horarios' => [
            'comando' => 'liga:actualizar-horarios-proximos',
            'nombre' => 'Actualizar horarios próximos',
            'cron' => '0 8 * * *',
            'frecuencia' => 'Cada día a las 08:00',
            'confirmar' => null,
        ],
    ];

    public const REIMPORTAR_JORNADA = 'reimportar-jornada';
    public const REIMPORTAR_PARTIDO = 'reimportar-partido';

    public const TOTAL_JORNADAS = 38;

    /** Minutos desde el inicio de un partido a partir de los cuales se da por terminado. */
    public const MINUTOS_PARTIDO_TERMINADO = 150;

    /** Un lanzamiento manual que lleva más de esto sin terminar se da por perdido (p. ej. lo cortó un despliegue). */
    public const MINUTOS_LANZAMIENTO_PERDIDO = 15;

    private const LARGO_MAXIMO_SALIDA = 6000;

    /**
     * Apunta la última pasada de una tarea PROGRAMADA. Lo llama routes/console.php
     * cada vez que el programador termina una. Una sola fila por tarea, que se va
     * actualizando — la sincronización corre 720 veces al día.
     *
     * Nunca lanza excepción: llevar el registro no puede romper al programador.
     */
    public static function registrarProgramada(string $comandoCompleto, bool $ok, ?float $segundos, ?string $detalle = null): void
    {
        try {
            foreach (self::PROGRAMADAS as $clave => $tarea) {
                if (! str_contains($comandoCompleto, $tarea['comando'])) continue;

                $fin = now();

                EjecucionTarea::updateOrCreate(
                    ['tarea' => $clave, 'origen' => 'programada'],
                    [
                        'estado' => $ok ? 'ok' : 'fallo',
                        'salida' => $detalle ? self::recortar($detalle) : null,
                        'iniciada_en' => $segundos !== null ? $fin->copy()->subMilliseconds((int) round($segundos * 1000)) : $fin,
                        'terminada_en' => $fin,
                    ]
                );

                return;
            }
        } catch (\Throwable $e) {
            // A propósito en silencio (ver comentario de arriba).
        }
    }

    public static function esReimportacion(string $clave): bool
    {
        return in_array($clave, [self::REIMPORTAR_JORNADA, self::REIMPORTAR_PARTIDO], true);
    }

    /** Se guarda el final de la salida, que es donde está el resultado. */
    public static function recortar(string $texto): string
    {
        $texto = trim($texto);

        if (strlen($texto) <= self::LARGO_MAXIMO_SALIDA) {
            return $texto;
        }

        return '[…] '.mb_strcut($texto, strlen($texto) - self::LARGO_MAXIMO_SALIDA);
    }
}
