<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;

/**
 * Revisión de la calidad de los datos de jugadores y plantillas de la
 * temporada en curso (/admin/calidad-datos). Solo lee: cada problema lleva a
 * la ficha del jugador para arreglarlo a mano.
 *
 * Complementa a Cambios de plantilla: aquella cola solo ve lo que LaLiga
 * trae distinto al importar un partido; esto mira la base de datos tal cual.
 */
class CalidadDatosService
{
    private const MAXIMO_FILAS = 200;

    public function revisar(): array
    {
        $idTemporada = (int) Temporada::orderByDesc('fecha_inicio')->value('id');

        $fichas = PlantillaTemporada::with(['jugador', 'equipo'])
            ->where('id_temporada', $idTemporada)
            ->get()
            ->filter(fn ($f) => $f->jugador !== null);

        $activas = $fichas->filter(fn ($f) => $f->fecha_salida === null);
        $nombre = fn ($j) => trim("{$j->nombre} {$j->apellidos}");
        $equipo = fn ($f) => $f->equipo->nombre_corto ?? $f->equipo->nombre ?? '?';
        $dia = fn ($fecha) => $fecha ? substr((string) $fecha, 0, 10) : null;

        $fila = fn ($jugador, ?string $nombreEquipo, string $detalle) => [
            'id_jugador' => $jugador->id,
            'nombre' => $nombre($jugador),
            'equipo' => $nombreEquipo,
            'detalle' => $detalle,
        ];

        $revisiones = [];

        // --- Errores: rompen el emparejamiento del importador o la plantilla ---

        $filas = [];
        foreach ($activas->groupBy('id_jugador') as $grupo) {
            if ($grupo->count() > 1) {
                $filas[] = $fila($grupo->first()->jugador, null, 'Fichas activas en: '.$grupo->map($equipo)->implode(', '));
            }
        }
        $revisiones[] = $this->revision('dos_fichas_activas', 'error', 'Jugadores en dos equipos a la vez',
            'Tienen más de una ficha sin fecha de salida. Al fichar por otro equipo hay que cerrar la anterior.', $filas);

        $filas = [];
        foreach ($fichas->groupBy('id_jugador') as $grupo) {
            $ordenadas = $grupo->sortBy(fn ($f) => $dia($f->fecha_incorporacion) ?? '0000-00-00')->values();
            for ($i = 1; $i < $ordenadas->count(); $i++) {
                $anterior = $ordenadas[$i - 1];
                $actual = $ordenadas[$i];
                $finAnterior = $dia($anterior->fecha_salida) ?? '9999-12-31';
                $inicioActual = $dia($actual->fecha_incorporacion) ?? '0000-00-00';
                if ($anterior->fecha_salida !== null && $inicioActual < $finAnterior) {
                    $filas[] = $fila($actual->jugador, null,
                        "{$equipo($anterior)} hasta el {$finAnterior}, pero {$equipo($actual)} desde el {$inicioActual}");
                }
            }
        }
        $revisiones[] = $this->revision('fechas_solapadas', 'error', 'Fichas con fechas que se pisan',
            'El mismo jugador figura en dos equipos durante unos días a la vez.', $filas);

        $filas = [];
        foreach ($activas->whereNotNull('dorsal')->groupBy(fn ($f) => $f->id_equipo.'|'.$f->dorsal) as $grupo) {
            if ($grupo->count() > 1) {
                foreach ($grupo as $f) {
                    $otros = $grupo->reject(fn ($o) => $o->id === $f->id)->map(fn ($o) => $nombre($o->jugador))->implode(', ');
                    $filas[] = $fila($f->jugador, $equipo($f), "Dorsal {$f->dorsal}, igual que {$otros}");
                }
            }
        }
        $revisiones[] = $this->revision('dorsal_repetido', 'error', 'Dorsales repetidos en un equipo',
            'Dos jugadores en activo del mismo equipo con el mismo número: confunde al emparejar por dorsal.', $filas);

        $revisiones[] = $this->revision('alineado_sin_ficha', 'error', 'Alineados cuando su ficha dice que no estaban',
            'LaLiga los alineó en un partido de un equipo en el que, según su ficha, no estaban ese día. Suele ser una fecha de incorporación posterior a sus primeros partidos.',
            $this->alineadosSinFicha($idTemporada, $fichas, $nombre, $equipo, $dia));

        $filas = [];
        foreach ($activas as $f) {
            if ($f->jugador->dado_de_baja_en !== null) {
                $filas[] = $fila($f->jugador, $equipo($f), 'Dado de baja, pero su ficha sigue abierta');
            }
        }
        $revisiones[] = $this->revision('baja_con_ficha', 'error', 'De baja con la ficha abierta',
            'Figuran como dados de baja pero siguen en la plantilla de un equipo.', $filas);

        // --- Avisos ---

        $conFichaActiva = $activas->pluck('id_jugador')->flip();
        $filas = Jugador::whereNull('dado_de_baja_en')->orderBy('nombre')->get()
            ->reject(fn ($j) => isset($conFichaActiva[$j->id]))
            ->map(fn ($j) => $fila($j, null, 'Sin equipo esta temporada'))
            ->values()->all();
        $revisiones[] = $this->revision('sin_equipo', 'aviso', 'Jugadores en activo sin equipo',
            'No están dados de baja pero no tienen ficha abierta en ningún equipo esta temporada.', $filas);

        $filas = $activas->whereNull('dorsal')->map(fn ($f) => $fila($f->jugador, $equipo($f), 'Sin dorsal'))->values()->all();
        $revisiones[] = $this->revision('sin_dorsal', 'aviso', 'Sin dorsal',
            'Sin dorsal el importador no puede usar el número para emparejarlos.', $filas);

        // --- Les falta algo (solo para que la app se vea completa) ---

        $jugadoresActivos = $activas->filter(fn ($f) => $f->jugador->dado_de_baja_en === null)->unique('id_jugador');

        $faltan = function (string $clave, string $titulo, callable $falta, string $detalle) use ($jugadoresActivos, $fila, $equipo) {
            $filas = $jugadoresActivos->filter(fn ($f) => $falta($f->jugador))
                ->map(fn ($f) => $fila($f->jugador, $equipo($f), $detalle))->values()->all();

            return $this->revision($clave, 'incompleto', $titulo, null, $filas);
        };

        $revisiones[] = $faltan('sin_foto', 'Sin foto', fn ($j) => blank($j->foto_url), 'Sin foto');
        $revisiones[] = $faltan('sin_camiseta', 'Sin nombre de camiseta', fn ($j) => blank($j->nombre_camiseta), 'Sin nombre de camiseta');
        $revisiones[] = $faltan('sin_datos', 'Sin fecha de nacimiento o nacionalidad',
            fn ($j) => $j->fecha_nacimiento === null || blank($j->nacionalidad), 'Falta fecha de nacimiento o nacionalidad');

        return ['revisiones' => $revisiones, 'jugadores_activos' => $jugadoresActivos->count()];
    }

    private function revision(string $clave, string $gravedad, string $titulo, ?string $explicacion, array $filas): array
    {
        usort($filas, fn ($a, $b) => [$a['equipo'] ?? '', $a['nombre']] <=> [$b['equipo'] ?? '', $b['nombre']]);

        return [
            'clave' => $clave,
            'gravedad' => $gravedad,
            'titulo' => $titulo,
            'explicacion' => $explicacion,
            'total' => count($filas),
            'filas' => array_slice($filas, 0, self::MAXIMO_FILAS),
        ];
    }

    /** Alineaciones de la temporada en las que el jugador no tenía ficha en ese equipo el día del partido. */
    private function alineadosSinFicha(int $idTemporada, $fichas, callable $nombre, callable $equipo, callable $dia): array
    {
        $fichasPorJugadorEquipo = $fichas->groupBy(fn ($f) => $f->id_jugador.'|'.$f->id_equipo);

        $alineaciones = AlineacionJugador::with(['jugador', 'equipo'])
            ->join('calendariopartidos', 'calendariopartidos.id', '=', 'alineaciones_jugador.id_partido')
            ->where('calendariopartidos.id_temporada', $idTemporada)
            ->whereNotNull('alineaciones_jugador.id_jugador')
            ->get(['alineaciones_jugador.*', 'calendariopartidos.horario_estimado as fecha_partido', 'calendariopartidos.jornada as jornada_partido']);

        $porJugadorEquipo = [];
        foreach ($alineaciones as $a) {
            if (! $a->jugador || ! $a->fecha_partido) {
                continue;
            }

            $fecha = substr((string) $a->fecha_partido, 0, 10);
            $cubre = ($fichasPorJugadorEquipo->get($a->id_jugador.'|'.$a->id_equipo) ?? collect())->contains(function ($f) use ($fecha, $dia) {
                $desde = $dia($f->fecha_incorporacion);
                $hasta = $dia($f->fecha_salida);

                return ($desde === null || $desde <= $fecha) && ($hasta === null || $hasta >= $fecha);
            });

            if (! $cubre) {
                $clave = $a->id_jugador.'|'.$a->id_equipo;
                $porJugadorEquipo[$clave]['jugador'] = $a->jugador;
                $porJugadorEquipo[$clave]['equipo'] = $a->equipo->nombre_corto ?? $a->equipo->nombre ?? '?';
                $porJugadorEquipo[$clave]['jornadas'][] = (int) $a->jornada_partido;
            }
        }

        $filas = [];
        foreach ($porJugadorEquipo as $datos) {
            $jornadas = array_unique($datos['jornadas']);
            sort($jornadas);
            $filas[] = [
                'id_jugador' => $datos['jugador']->id,
                'nombre' => $nombre($datos['jugador']),
                'equipo' => $datos['equipo'],
                'detalle' => 'Alineado en la jornada '.implode(', ', $jornadas).' sin ficha que cubra esa fecha',
            ];
        }

        return $filas;
    }
}
