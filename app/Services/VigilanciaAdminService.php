<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\CierreJornada;
use App\Models\EjecucionTarea;
use App\Models\EventoPartido;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use App\Notifications\AvisoAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * La vigilancia que avisa al admin por la campana (comando admin:vigilar, cada
 * 5 minutos). Tres cosas, y de cada una se avisa UNA sola vez:
 *
 *   - una jornada ya se puede cerrar en una liga
 *   - una tarea del panel ha terminado con fallo
 *   - ha aparecido un error nuevo en los registros
 *
 * Los avisos van a los superadmin, que son quienes tienen esas pantallas.
 *
 * Solo mira y avisa: no cierra, no reintenta y no cambia nada del juego.
 */
class VigilanciaAdminService
{
    /** Solo se avisa de jornadas cuyo último partido es de estos días (no de las antiguas). */
    private const DIAS_JORNADA_RECIENTE = 7;

    /** Si los datos de LaLiga no han llegado este rato después del último partido, se avisa igualmente. */
    private const MINUTOS_ESPERA_DATOS = 240;

    private const MINUTOS_FALLO_RECIENTE = 30;
    private const MAXIMO_ERRORES_POR_PASADA = 3;

    public function __construct(private VisorErroresService $visor) {}

    /**
     * @return array{jornadas:int,tareas:int,errores:int} cuántos avisos se han enviado
     */
    public function revisar(?string $directorioRegistros = null): array
    {
        return [
            'jornadas' => $this->jornadasListas(),
            'tareas' => $this->tareasFallidas(),
            'errores' => $this->erroresNuevos($directorioRegistros ?? storage_path('logs')),
        ];
    }

    /** true la primera vez que se pide esa clave; false si ya se avisó de eso. */
    private function esNuevo(string $clave): bool
    {
        return DB::table('avisos_admin')->insertOrIgnore(['clave' => $clave, 'created_at' => now()]) > 0;
    }

    private function superadmins()
    {
        return User::where('es_superadmin', true)->get();
    }

    // ------------------------------------------------------------------

    private function jornadasListas(): int
    {
        $idTemporada = (int) Temporada::orderByDesc('fecha_inicio')->value('id');
        $ligas = Liga::where('id_temporada', $idTemporada)->get();

        if ($ligas->isEmpty()) {
            return 0;
        }

        $porJornada = CalendarioPartido::where('id_temporada', $idTemporada)
            ->whereBetween('jornada', [1, TareasAdmin::TOTAL_JORNADAS])
            ->get(['id', 'jornada', 'estado', 'horario_estimado'])
            ->groupBy('jornada');

        $enviados = 0;

        foreach ($porJornada as $jornada => $partidos) {
            if ($partidos->contains(fn ($p) => $p->estado !== 'Jugado')) {
                continue;
            }

            $ultimo = $partidos->pluck('horario_estimado')->filter()->max();
            if (! $ultimo || $ultimo->lt(now()->subDays(self::DIAS_JORNADA_RECIENTE))) {
                continue;
            }

            $ids = $partidos->pluck('id');
            $conEventos = EventoPartido::whereIn('id_partido', $ids)->distinct()->pluck('id_partido')->flip();
            $conAlineacion = AlineacionJugador::whereIn('id_partido', $ids)->distinct()->pluck('id_partido')->flip();
            $sinDatos = $ids->reject(fn ($id) => isset($conEventos[$id]) && isset($conAlineacion[$id]))->count();

            // Los datos de LaLiga llegan unos minutos después de cada partido: se espera
            // un rato razonable antes de avisar de una jornada a la que aún le faltan.
            if ($sinDatos > 0 && now()->lt(Carbon::parse($ultimo)->addMinutes(self::MINUTOS_ESPERA_DATOS))) {
                continue;
            }

            foreach ($ligas as $liga) {
                $cerrada = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();
                $hayPronosticos = Pronostico::where('id_liga', $liga->id)->whereIn('id_partido', $ids)->exists();

                if ($cerrada || ! $hayPronosticos || ! $this->esNuevo("cerrar:{$liga->id}:{$jornada}")) {
                    continue;
                }

                $mensaje = $sinDatos === 0
                    ? "Ya se han jugado los {$partidos->count()} partidos de la jornada {$jornada} y han llegado los datos de LaLiga. Puedes cerrarla en {$liga->nombre}."
                    : "Ya se han jugado los {$partidos->count()} partidos de la jornada {$jornada}, pero faltan datos de LaLiga en {$sinDatos}. Puedes cerrarla en {$liga->nombre}; revisa esos partidos antes de calcular goleadores.";

                // A los superadmin: son quienes tienen la pantalla de Cierre de jornada.
                $admins = $this->superadmins();
                Notification::send($admins, new AvisoAdmin('admin_jornada_lista', "✅ Jornada {$jornada} lista para cerrar", $mensaje, true));
                $enviados += $admins->count();
            }
        }

        return $enviados;
    }

    private function tareasFallidas(): int
    {
        $fallidas = EjecucionTarea::where('estado', 'fallo')
            ->where('terminada_en', '>=', now()->subMinutes(self::MINUTOS_FALLO_RECIENTE))
            ->get();

        $enviados = 0;

        foreach ($fallidas as $ejecucion) {
            $programada = $ejecucion->origen === 'programada';

            // Una tarea programada que falla lo hace cada pocos minutos: un aviso al día por tarea.
            $clave = $programada
                ? "tarea:{$ejecucion->tarea}:".now()->toDateString()
                : "lanzamiento:{$ejecucion->id}";

            if (! $this->esNuevo($clave)) {
                continue;
            }

            $nombre = TareasAdmin::PROGRAMADAS[$ejecucion->tarea]['nombre']
                ?? TareasAdmin::MANUALES[$ejecucion->tarea]['nombre']
                ?? $ejecucion->tarea;
            $detalle = trim((string) $ejecucion->salida);
            $mensaje = "«{$nombre}» ha terminado con fallo".($detalle !== '' ? ': '.mb_substr($detalle, -200) : '.');
            if ($programada) {
                $mensaje .= ' No volveré a avisar hoy de esta tarea aunque siga fallando: mírala en el panel.';
            }

            $destinatarios = $this->superadmins();
            Notification::send($destinatarios, new AvisoAdmin('admin_tarea_fallida', '⚠️ Una tarea ha fallado', $mensaje));
            $enviados += $destinatarios->count();
        }

        return $enviados;
    }

    private function erroresNuevos(string $directorio): int
    {
        $limite = now()->subMinutes(self::MINUTOS_FALLO_RECIENTE);
        $enviados = 0;
        $avisados = 0;

        foreach ($this->visor->ultimos($directorio, base_path(), 20)['errores'] as $error) {
            if ($avisados >= self::MAXIMO_ERRORES_POR_PASADA) {
                break;
            }

            if (! $error['cuando'] || Carbon::parse($error['cuando'])->lt($limite)) {
                continue;
            }

            // Mismo fallo = misma clave: un aviso por fallo distinto y día.
            $firma = md5($error['nivel'].'|'.$error['clase'].'|'.$error['archivo'].'|'.$error['linea'].'|'.preg_replace('/\d+/', '#', $error['mensaje']));
            if (! $this->esNuevo("error:{$firma}:".now()->toDateString())) {
                continue;
            }

            $mensaje = mb_substr($error['mensaje'] ?: '(sin mensaje)', 0, 200);
            if ($error['archivo']) {
                $mensaje .= " ({$error['archivo']}:{$error['linea']})";
            }
            if ($error['veces'] > 1) {
                $mensaje .= " · {$error['veces']} veces";
            }

            $destinatarios = $this->superadmins();
            Notification::send($destinatarios, new AvisoAdmin('admin_error_nuevo', '🛑 Error nuevo en la aplicación', $mensaje));
            $enviados += $destinatarios->count();
            $avisados++;
        }

        return $enviados;
    }
}
