<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\CambioPlantilla;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\Temporada;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * La lista de cierre de una jornada (/admin/cierre-jornada): qué está hecho,
 * qué falta y qué paso toca ahora, para cada liga.
 *
 * Aquí solo se MIRA. Las acciones (cerrar, calcular goleadores, repartir
 * cartas) las siguen haciendo los controladores de siempre; esta clase
 * únicamente comprueba lo mismo que ellos exigen y en el orden que importa:
 *
 *   partidos jugados → cerrar → goleadores (necesita los eventos) → repartir
 *   cartas (el Top 3 se calcula con los puntos que haya en ese momento).
 */
class CierreJornadaService
{
    /** Orígenes de carta que cuentan como "el reparto semanal de esa jornada ya se hizo". */
    public const ORIGENES_DE_REPARTO = ['reparto_semanal', 'bonus_top3'];

    public function estado(int $jornada, User $usuario): array
    {
        $idTemporada = $this->temporadaActual();
        $partidos = $this->estadoDeLosPartidos($idTemporada, $jornada);

        $estadoLigas = [];
        foreach ($this->ligasDeLaTemporada($idTemporada) as $liga) {
            $estadoLigas[] = $this->estadoDeLiga($liga, $jornada, $partidos, $usuario);
        }

        unset($partidos['ids']);

        return ['jornada' => $jornada, 'partidos' => $partidos, 'ligas' => $estadoLigas];
    }

    /**
     * La última jornada de la temporada actual que ya tiene algún partido
     * jugado: la que normalmente toca cerrar. Nunca pasa del número de jornadas
     * de la liga, aunque en la base de datos haya partidos con una jornada mayor.
     */
    public function jornadaSugerida(): int
    {
        $jornada = CalendarioPartido::where('id_temporada', $this->temporadaActual())
            ->where('estado', 'Jugado')
            ->whereBetween('jornada', [1, TareasAdmin::TOTAL_JORNADAS])
            ->max('jornada');

        return (int) ($jornada ?: 1);
    }

    /**
     * Desde qué jornada hay cartas en una liga: la primera cuyo grueso de
     * partidos empieza DESPUÉS de que la liga recibiera su primera carta. Las
     * cartas no tienen efecto hacia atrás: una jornada que ya había empezado
     * cuando se repartieron (o una anterior con un partido aplazado todavía
     * por jugar) no tiene ni cartas que resolver ni reparto semanal.
     *
     * null = la liga no tiene cartas, o todavía no se le ha repartido ninguna.
     */
    public function primeraJornadaConCartas(Liga $liga): ?int
    {
        if ($liga->tipo !== 'ConExtras') {
            return null;
        }

        $primeraCarta = CartaUsuario::where('id_liga', $liga->id)->min('obtenida_en');
        if (! $primeraCarta) {
            return null;
        }

        return $this->primeraJornadaQueEmpiezaDespuesDe((int) $liga->id_temporada, Carbon::parse($primeraCarta));
    }

    /**
     * La primera jornada cuyo inicio real cae después de ese momento. El inicio
     * real es el primer partido del grueso de la jornada (mediana ± 5 días, la
     * misma regla de jornadaBloqueada): un adelantado o un aplazado no cuenta.
     */
    public function primeraJornadaQueEmpiezaDespuesDe(int $idTemporada, CarbonInterface $momento): ?int
    {
        $porJornada = CalendarioPartido::where('id_temporada', $idTemporada)
            ->whereNotNull('horario_estimado')
            ->whereBetween('jornada', [1, TareasAdmin::TOTAL_JORNADAS])
            ->get(['id', 'jornada', 'horario_estimado'])
            ->groupBy('jornada')
            ->sortKeys();

        $ventanaSegundos = 5 * 24 * 60 * 60;

        foreach ($porJornada as $jornada => $partidos) {
            $timestamps = $partidos->map(fn ($p) => $p->horario_estimado->timestamp)->sort()->values();
            $mediana = $timestamps[intdiv($timestamps->count(), 2)];
            $inicio = $timestamps->filter(fn ($t) => abs($t - $mediana) <= $ventanaSegundos)->min();

            if ($inicio > $momento->timestamp) {
                return (int) $jornada;
            }
        }

        return null;
    }

    /** La temporada en curso: la de fecha de inicio más reciente (igual que el importador de LaLiga). */
    private function temporadaActual(): int
    {
        return (int) Temporada::orderByDesc('fecha_inicio')->value('id');
    }

    private function ligasDeLaTemporada(int $idTemporada)
    {
        return Liga::where('id_temporada', $idTemporada)->orderBy('id')->get();
    }

    /**
     * Jornadas ya empezadas que alguna liga tiene sin cerrar — para que una que
     * se quedó atrás (por un aplazado, p. ej.) no se olvide.
     *
     * Solo cuentan las ligas en las que alguien pronosticó esa jornada: una liga
     * sin pronósticos (una de pruebas, o una creada a mitad de temporada) no
     * tiene nada que cerrar ahí y solo haría ruido en la lista.
     *
     * @return array<int,array{jornada:int,ligas:array<int,string>,partidos_pendientes:int}>
     */
    public function jornadasSinCerrar(): array
    {
        $idTemporada = $this->temporadaActual();
        $ligas = $this->ligasDeLaTemporada($idTemporada);
        if ($ligas->isEmpty()) {
            return [];
        }

        $hasta = $this->jornadaSugerida();
        $cerradas = CierreJornada::where('cerrada', true)->get()->groupBy('jornada');
        $pendientesPorJornada = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('jornada', '<=', $hasta)
            ->where('estado', '!=', 'Jugado')
            ->get()
            ->countBy('jornada');

        $conPronosticos = [];
        $filas = Pronostico::join('calendariopartidos', 'calendariopartidos.id', '=', 'pronosticos.id_partido')
            ->where('calendariopartidos.id_temporada', $idTemporada)
            ->where('calendariopartidos.jornada', '<=', $hasta)
            ->select('pronosticos.id_liga', 'calendariopartidos.jornada')
            ->distinct()
            ->get();
        foreach ($filas as $fila) {
            $conPronosticos[((int) $fila->id_liga).'|'.((int) $fila->jornada)] = true;
        }

        $resultado = [];
        for ($jornada = 1; $jornada <= $hasta; $jornada++) {
            $idsCerradas = ($cerradas->get($jornada) ?? collect())->pluck('id_liga')->all();
            $sinCerrar = $ligas
                ->reject(fn ($liga) => in_array($liga->id, $idsCerradas))
                ->filter(fn ($liga) => isset($conPronosticos[$liga->id.'|'.$jornada]))
                ->pluck('nombre')->values()->all();

            if ($sinCerrar) {
                $resultado[] = [
                    'jornada' => $jornada,
                    'ligas' => $sinCerrar,
                    'partidos_pendientes' => (int) $pendientesPorJornada->get($jornada, 0),
                ];
            }
        }

        return $resultado;
    }

    // ------------------------------------------------------------------

    /** Lo que es igual para todas las ligas: los partidos de la jornada y sus datos de LaLiga. */
    private function estadoDeLosPartidos(int $idTemporada, int $jornada): array
    {
        $partidos = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
            ->where('id_temporada', $idTemporada)
            ->where('jornada', $jornada)
            ->orderBy('horario_estimado')
            ->get();

        if ($partidos->isEmpty()) {
            return $this->bloqueVacio();
        }

        $ids = $partidos->pluck('id');
        $conEventos = EventoPartido::whereIn('id_partido', $ids)->distinct()->pluck('id_partido')->flip();
        $conAlineacion = AlineacionJugador::whereIn('id_partido', $ids)->distinct()->pluck('id_partido')->flip();

        $etiqueta = fn ($p) => ($p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre ?? '?').' - '.($p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre ?? '?');

        $sinJugar = $partidos->where('estado', '!=', 'Jugado')->map(fn ($p) => [
            'id' => $p->id,
            'partido' => $etiqueta($p),
            'estado' => $p->estado,
            'horario' => $p->horario_estimado?->toIso8601String(),
        ])->values();

        $sinDatos = $partidos->where('estado', 'Jugado')
            ->filter(fn ($p) => ! isset($conEventos[$p->id]) || ! isset($conAlineacion[$p->id]))
            ->map(fn ($p) => [
                'id' => $p->id,
                'partido' => $etiqueta($p),
                'falta' => ! isset($conEventos[$p->id]) && ! isset($conAlineacion[$p->id])
                    ? 'eventos y alineación'
                    : (! isset($conEventos[$p->id]) ? 'eventos' : 'alineación'),
            ])->values();

        $idsLista = $ids->map(fn ($id) => (int) $id)->all();
        $cambiosPendientes = CambioPlantilla::where('estado', 'pendiente')
            ->where('tipo', 'jugador')
            ->get()
            ->filter(fn ($cambio) => array_intersect(array_map('intval', $cambio->partidos ?? []), $idsLista))
            ->count();

        return [
            'ids' => $ids,
            'total' => $partidos->count(),
            'jugados' => $partidos->where('estado', 'Jugado')->count(),
            'sin_jugar' => $sinJugar,
            'sin_datos' => $sinDatos,
            'cambios_plantilla_pendientes' => $cambiosPendientes,
        ];
    }

    private function bloqueVacio(): array
    {
        return ['ids' => collect(), 'total' => 0, 'jugados' => 0, 'sin_jugar' => collect(), 'sin_datos' => collect(), 'cambios_plantilla_pendientes' => 0];
    }

    private function estadoDeLiga(Liga $liga, int $jornada, array $partidos, User $usuario): array
    {
        $ids = $partidos['ids'];
        $conCartas = $liga->tipo === 'ConExtras';

        // Las cartas no son retroactivas: en las jornadas anteriores a su inicio no hay nada de cartas que hacer.
        $cartasDesde = $conCartas ? $this->primeraJornadaConCartas($liga) : null;
        $jornadaSinCartas = $cartasDesde !== null && $jornada < $cartasDesde;

        $soyAdmin = $liga->usuarios()->where('id_usuario', $usuario->id)->wherePivot('rol', 'Admin')->exists();
        $miembros = $liga->usuarios()->count();

        $idsConPronostico = Pronostico::where('id_liga', $liga->id)->whereIn('id_partido', $ids)->distinct()->pluck('id_usuario');
        $idsConPuntos = EventoPuntos::where('id_liga', $liga->id)->where('jornada', $jornada)->distinct()->pluck('id_usuario');

        $cierre = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->first();
        $cerrada = (bool) ($cierre?->cerrada);
        $goleadoresCalculados = $cierre?->goleadores_calculados_en !== null;
        $cerradaPor = $cierre?->cerrada_por ? User::find($cierre->cerrada_por) : null;

        $seleccionesGoleadores = GoleadorJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->count();

        $cartasEsperando = 0;
        $cartasResueltas = 0;
        $cartasRepartidas = 0;
        if ($conCartas) {
            $cartasEsperando = CartaUsuario::where('id_liga', $liga->id)->where('jornada_efecto', $jornada)->where('estado', 'jugada')->count();
            $cartasResueltas = CartaUsuario::where('id_liga', $liga->id)->where('jornada_efecto', $jornada)->whereIn('estado', ['resuelta_cumplida', 'resuelta_no_cumplida'])->count();
            $cartasRepartidas = CartaUsuario::where('id_liga', $liga->id)->where('jornada_obtenida', $jornada)->whereIn('origen', self::ORIGENES_DE_REPARTO)->count();
        }

        $faltanPorJugar = $partidos['total'] - $partidos['jugados'];
        $faltanDatos = count($partidos['sin_datos']);
        $sinPuntos = $cerrada ? $idsConPronostico->diff($idsConPuntos)->count() : 0;

        $pasos = [];

        // 1. Cerrar
        if ($cerrada) {
            $quien = $cerradaPor ? ' por '.($cerradaPor->nombre_visible ?? $cerradaPor->name) : '';
            $detalle = 'Cerrada el '.$cierre->cerrada_en?->format('d/m H:i').$quien.'.';
            if ($sinPuntos > 0) {
                $pasos[] = $this->paso('cerrar', 'Cerrar la jornada', 'aviso', $detalle." Ojo: {$sinPuntos} usuario(s) pronosticaron y no tienen puntos apuntados.");
            } else {
                $pasos[] = $this->paso('cerrar', 'Cerrar la jornada', 'hecho', $detalle);
            }
        } elseif ($partidos['total'] === 0) {
            $pasos[] = $this->paso('cerrar', 'Cerrar la jornada', 'bloqueado', 'Esta jornada no tiene partidos cargados.');
        } elseif ($faltanPorJugar > 0) {
            $pasos[] = $this->paso('cerrar', 'Cerrar la jornada', 'bloqueado', "Faltan {$faltanPorJugar} partido(s) por jugar: no se puede cerrar hasta que estén los {$partidos['total']}.");
        } else {
            $pasos[] = $this->paso('cerrar', 'Cerrar la jornada', 'listo', 'Calcula los puntos de los pronósticos y el bonus de pleno, resuelve las cartas de resultado y las faltas, y avisa a los jugadores.', 'cerrar');
        }

        // 2. Goleadores (y cartas que dependen de goles y tarjetas)
        $loQueHace = $conCartas && ! $jornadaSinCartas ? 'Da los puntos de goleadores y resuelve las cartas que dependen de goles y tarjetas.' : 'Da los puntos de goleadores.';
        if (! $cerrada) {
            $pasos[] = $this->paso('goleadores', 'Calcular goleadores', 'bloqueado', 'Primero hay que cerrar la jornada.');
        } elseif ($goleadoresCalculados) {
            $detalle = 'Calculados el '.$cierre->goleadores_calculados_en->format('d/m H:i').'. Se puede repetir si después cambian los eventos de algún partido.';
            $pasos[] = $this->paso('goleadores', 'Calcular goleadores', 'hecho', $detalle, 'goleadores');
        } elseif ($faltanDatos > 0) {
            $pasos[] = $this->paso('goleadores', 'Calcular goleadores', 'aviso', "Faltan datos de LaLiga en {$faltanDatos} partido(s): si calculas ahora, los goles de esos partidos no cuentan. {$loQueHace}", 'goleadores');
        } else {
            $pasos[] = $this->paso('goleadores', 'Calcular goleadores', 'listo', $loQueHace, 'goleadores');
        }

        // 3. Reparto semanal de cartas
        if ($conCartas) {
            if ($jornadaSinCartas) {
                $pasos[] = $this->paso('repartir', 'Repartir las cartas de la semana', 'no_aplica', "Las cartas de esta liga empiezan en la jornada {$cartasDesde}: esta jornada no tiene reparto.");
            } elseif ($cartasRepartidas > 0) {
                $pasos[] = $this->paso('repartir', 'Repartir las cartas de la semana', 'hecho', "{$cartasRepartidas} carta(s) repartidas por esta jornada.");
            } elseif (! $cerrada) {
                $pasos[] = $this->paso('repartir', 'Repartir las cartas de la semana', 'bloqueado', 'Primero hay que cerrar la jornada.');
            } elseif (! $goleadoresCalculados) {
                $pasos[] = $this->paso('repartir', 'Repartir las cartas de la semana', 'aviso', 'Mejor después de calcular goleadores: el bonus del Top 3 se decide con los puntos que haya en este momento.', 'repartir');
            } else {
                $pasos[] = $this->paso('repartir', 'Repartir las cartas de la semana', 'listo', 'Una tanda por jugador según la configuración de la liga, más el bonus para el Top 3 de la jornada.', 'repartir');
            }
        }

        return [
            'id' => $liga->id,
            'nombre' => $liga->nombre,
            'con_cartas' => $conCartas,
            'cartas_desde_jornada' => $cartasDesde,
            'soy_admin' => $soyAdmin,
            'miembros' => $miembros,
            'con_pronostico' => $idsConPronostico->count(),
            'goleadores_elegidos' => $seleccionesGoleadores,
            'cartas_esperando' => $cartasEsperando,
            'cartas_resueltas' => $cartasResueltas,
            'pasos' => $pasos,
            'completa' => collect($pasos)->every(fn ($paso) => in_array($paso['estado'], ['hecho', 'no_aplica'], true)),
        ];
    }

    /** @param string $estado hecho | listo | aviso | bloqueado | no_aplica */
    private function paso(string $clave, string $titulo, string $estado, string $detalle, ?string $accion = null): array
    {
        return ['clave' => $clave, 'titulo' => $titulo, 'estado' => $estado, 'detalle' => $detalle, 'accion' => $accion];
    }
}
