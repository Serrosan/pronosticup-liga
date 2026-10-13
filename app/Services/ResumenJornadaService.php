<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El resumen de una jornada YA CERRADA en una liga: lo que se comenta el lunes.
 * Pestaña "Resumen" de La jornada en tu liga.
 *
 * Solo lee lo que el cierre dejó guardado (eventos_puntos, cartas resueltas,
 * eventos de los partidos). No calcula puntos ni toca el cierre. Cada
 * "destacado" es opcional: si no hay de qué hablar, no sale.
 */
class ResumenJornadaService
{
    private const ACIERTOS = ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'];

    /** Con cuántos pronósticos en un partido tiene gracia "contra todos". */
    private const MINIMO_PARA_CONTRA_TODOS = 4;

    public function estaCerrada(Liga $liga, int $jornada): bool
    {
        return CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();
    }

    public function resumen(Liga $liga, int $jornada, User $yo): ?array
    {
        if (! $this->estaCerrada($liga, $jornada)) {
            return null;
        }

        $miembros = $liga->usuarios()->get()->keyBy('id');
        $eventos = EventoPuntos::where('id_liga', $liga->id)->where('jornada', '<=', $jornada)->get(['id_usuario', 'id_partido', 'jornada', 'tipo_evento', 'puntos']);
        $deEsta = $eventos->where('jornada', $jornada);

        if ($deEsta->isEmpty()) {
            return null;
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->get()
            ->keyBy('id');

        $puntosJornada = $this->sumaPorUsuario($deEsta, $miembros);
        $clasificacion = $this->movimientos($eventos, $jornada, $miembros);

        $persona = fn ($id) => [
            'id_usuario' => (int) $id,
            'usuario' => $this->nombre($miembros->get($id)),
            'avatar_url' => $this->avatar($miembros->get($id)),
            'soy_yo' => (int) $id === $yo->id,
        ];

        // Podio y farolillo, con empates.
        $ordenados = $puntosJornada->sortDesc();
        $podio = [];
        $posicion = 0;
        $anterior = null;
        foreach ($ordenados as $id => $puntos) {
            if ($puntos !== $anterior) {
                $posicion = count($podio) + 1;
                $anterior = $puntos;
            }
            if ($posicion > 3) {
                break;
            }
            $podio[] = $persona($id) + ['puntos' => $puntos, 'posicion' => $posicion];
        }

        $minimo = $ordenados->min();
        $farolillo = $ordenados->count() > 3
            ? $ordenados->filter(fn ($p) => $p === $minimo)->keys()->map(fn ($id) => $persona($id) + ['puntos' => $minimo])->values()->all()
            : [];

        return [
            'podio' => $podio,
            'farolillo' => $farolillo,
            'media' => $ordenados->count() ? round($ordenados->avg(), 1) : null,
            'sube' => $clasificacion['sube'] ? $persona($clasificacion['sube']['id']) + $clasificacion['sube'] : null,
            'baja' => $clasificacion['baja'] ? $persona($clasificacion['baja']['id']) + $clasificacion['baja'] : null,
            'lider' => $clasificacion['lider'] ? $persona($clasificacion['lider']['id']) + $clasificacion['lider'] : null,
            'mas_exactos' => $this->masExactos($deEsta, $persona),
            'exactos_unicos' => $this->exactosUnicos($liga, $deEsta, $partidos, $persona),
            'contra_todos' => $this->contraTodos($liga, $deEsta, $partidos, $persona),
            'mejor_carta' => $liga->tipo === 'ConExtras' ? $this->mejorCarta($liga, $jornada, $persona, $yo) : null,
            'goleador' => $this->goleador($liga, $jornada, $partidos, $persona),
        ];
    }

    // ------------------------------------------------------------------

    private function sumaPorUsuario(Collection $eventos, Collection $miembros): Collection
    {
        return $eventos
            ->groupBy('id_usuario')
            ->map(fn ($grupo) => (int) $grupo->sum('puntos'))
            ->filter(fn ($puntos, $id) => $miembros->has($id));
    }

    /**
     * Puesto de cada uno antes y después de esta jornada (por puntos totales,
     * los empatados comparten puesto) y quién se movió más.
     */
    private function movimientos(Collection $eventos, int $jornada, Collection $miembros): array
    {
        $antes = $this->puestos($this->sumaPorUsuario($eventos->where('jornada', '<', $jornada), $miembros), $miembros);
        $despues = $this->puestos($this->sumaPorUsuario($eventos, $miembros), $miembros);

        $sube = null;
        $baja = null;
        foreach ($despues as $id => $puesto) {
            $cambio = ($antes[$id] ?? $puesto) - $puesto;
            if ($cambio > 0 && (! $sube || $cambio > $sube['puestos'])) {
                $sube = ['id' => $id, 'puestos' => $cambio, 'puesto' => $puesto];
            }
            if ($cambio < 0 && (! $baja || -$cambio > $baja['puestos'])) {
                $baja = ['id' => $id, 'puestos' => -$cambio, 'puesto' => $puesto];
            }
        }

        $lider = null;
        $lideres = array_keys($despues, 1, true);
        if (count($lideres) === 1) {
            $id = $lideres[0];
            $eraLider = ($antes[$id] ?? null) === 1;
            $lider = ['id' => $id, 'nuevo' => ! $eraLider];
        }

        return ['sube' => $sube, 'baja' => $baja, 'lider' => $lider];
    }

    /** @return array<int,int> puesto por id de usuario (todos los miembros, con 0 puntos si no tienen) */
    private function puestos(Collection $puntos, Collection $miembros): array
    {
        $todos = $miembros->keys()->mapWithKeys(fn ($id) => [$id => (int) ($puntos[$id] ?? 0)])->sortDesc();

        $puestos = [];
        $puesto = 0;
        $vistos = 0;
        $anterior = null;
        foreach ($todos as $id => $p) {
            $vistos++;
            if ($p !== $anterior) {
                $puesto = $vistos;
                $anterior = $p;
            }
            $puestos[$id] = $puesto;
        }

        return $puestos;
    }

    private function masExactos(Collection $deEsta, callable $persona): ?array
    {
        $exactos = $deEsta->where('tipo_evento', 'AciertoExacto')->countBy('id_usuario');
        if ($exactos->isEmpty()) {
            return null;
        }

        $maximo = $exactos->max();
        if ($maximo < 2) {
            return null; // un exacto suelto no es noticia; ya sale en "nadie más lo vio"
        }

        return [
            'exactos' => $maximo,
            'quienes' => $exactos->filter(fn ($n) => $n === $maximo)->keys()->map($persona)->values()->all(),
        ];
    }

    /** Exactos que solo clavó una persona en ese partido. */
    private function exactosUnicos(Liga $liga, Collection $deEsta, Collection $partidos, callable $persona): array
    {
        $porPartido = $deEsta->where('tipo_evento', 'AciertoExacto')->whereNotNull('id_partido')->groupBy('id_partido');

        $unicos = [];
        foreach ($porPartido as $idPartido => $grupo) {
            if ($grupo->count() !== 1 || ! $partidos->has($idPartido)) {
                continue;
            }
            $partido = $partidos[$idPartido];
            $unicos[] = $persona($grupo->first()->id_usuario) + [
                'partido' => $this->etiqueta($partido),
                'marcador' => "{$partido->goles_casa}-{$partido->goles_fuera}",
                'pronosticaron' => Pronostico::where('id_liga', $liga->id)->where('id_partido', $idPartido)->count(),
            ];
        }

        // Los más meritorios primero: los de partidos con más gente pronosticando.
        usort($unicos, fn ($a, $b) => $b['pronosticaron'] <=> $a['pronosticaron']);

        return array_slice($unicos, 0, 3);
    }

    /** Quien fue el único con un signo en un partido... y acertó. */
    private function contraTodos(Liga $liga, Collection $deEsta, Collection $partidos, callable $persona): array
    {
        $aciertos = $deEsta->whereIn('tipo_evento', self::ACIERTOS)->whereNotNull('id_partido')
            ->map(fn ($e) => $e->id_partido.'-'.$e->id_usuario)->flip();

        $pronosticos = Pronostico::where('id_liga', $liga->id)->whereIn('id_partido', $partidos->keys())->get()->groupBy('id_partido');

        $valientes = [];
        foreach ($pronosticos as $idPartido => $grupo) {
            if ($grupo->count() < self::MINIMO_PARA_CONTRA_TODOS) {
                continue;
            }
            $porSigno = $grupo->groupBy(fn ($p) => $this->signo($p->goles_local_predicho, $p->goles_visitante_predicho));
            foreach ($porSigno as $signo => $quienes) {
                $solo = $quienes->first();
                if ($quienes->count() === 1 && isset($aciertos[$idPartido.'-'.$solo->id_usuario])) {
                    $valientes[] = $persona($solo->id_usuario) + [
                        'partido' => $this->etiqueta($partidos[$idPartido]),
                        'signo' => $signo,
                        'contra' => $grupo->count() - 1,
                    ];
                }
            }
        }

        usort($valientes, fn ($a, $b) => $b['contra'] <=> $a['contra']);

        return array_slice($valientes, 0, 3);
    }

    private function mejorCarta(Liga $liga, int $jornada, callable $persona, User $yo): ?array
    {
        $carta = CartaUsuario::where('id_liga', $liga->id)
            ->where('jornada_efecto', $jornada)
            ->where('estado', 'resuelta_cumplida')
            ->where('puntos_generados', '>', 0)
            ->with(['tipoCarta', 'partido.equipoLocal', 'partido.equipoVisitante'])
            ->orderByDesc('puntos_generados')
            ->orderBy('jugada_en')
            ->first();

        if (! $carta) {
            return null;
        }

        return $persona($carta->id_usuario) + [
            'carta' => $carta->tipoCarta?->nombre ?? 'Carta',
            'puntos' => (int) $carta->puntos_generados,
            'partido' => $carta->partido ? $this->etiqueta($carta->partido) : null,
        ];
    }

    /** El jugador elegido que más goles metió, y a quién le dio puntos. */
    private function goleador(Liga $liga, int $jornada, Collection $partidos, callable $persona): ?array
    {
        $elegidos = GoleadorJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->with('jugador')->get()->groupBy('id_jugador');
        if ($elegidos->isEmpty()) {
            return null;
        }

        $goles = DB::table('eventos_partido')
            ->whereIn('id_partido', $partidos->keys())
            ->whereIn('id_jugador', $elegidos->keys())
            ->where('tipo_evento', 'gol')
            ->selectRaw('id_jugador, COUNT(*) as goles')
            ->groupBy('id_jugador')
            ->pluck('goles', 'id_jugador');

        if ($goles->isEmpty()) {
            return null;
        }

        $maximo = (int) $goles->max();
        $idJugador = $goles->filter(fn ($g) => (int) $g === $maximo)
            ->keys()
            ->sortByDesc(fn ($id) => $elegidos[$id]->count())
            ->first();

        $jugador = $elegidos[$idJugador]->first()->jugador;

        return [
            'id_jugador' => (int) $idJugador,
            'nombre' => $jugador ? ($jugador->nombre_camiseta ?: trim("{$jugador->nombre} {$jugador->apellidos}")) : 'Jugador',
            'foto_url' => $jugador?->foto_url,
            'goles' => $maximo,
            'quienes' => $elegidos[$idJugador]->pluck('id_usuario')->map($persona)->values()->all(),
        ];
    }

    // ------------------------------------------------------------------

    private function etiqueta(CalendarioPartido $partido): string
    {
        $nombre = fn ($e) => $e?->nombre_corto ?: ($e?->nombre ?? '?');

        return $nombre($partido->equipoLocal).' - '.$nombre($partido->equipoVisitante);
    }

    private function signo($local, $visitante): string
    {
        return match (true) {
            (int) $local > (int) $visitante => 'Local',
            (int) $local < (int) $visitante => 'Visitante',
            default => 'Empate',
        };
    }

    private function nombre(?User $usuario): string
    {
        return $usuario ? ($usuario->nombre_visible ?? $usuario->name) : 'Alguien';
    }

    private function avatar(?User $usuario): ?string
    {
        return $usuario?->avatar_url ? url($usuario->avatar_url) : null;
    }
}
