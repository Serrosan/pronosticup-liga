<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EstadisticaPartido;
use App\Models\EventoPartido;
use App\Models\Jugador;
use Illuminate\Support\Str;

/**
 * Sustituye a ParserComentariosPartido: en vez de interpretar el texto pegado a
 * mano de "Comentarios", procesa el JSON estructurado que trae la propia página
 * de LaLiga (bloque __NEXT_DATA__, capturado por un script externo — nunca por
 * un scraper automático corriendo dentro de esta app).
 *
 * Produce EXACTAMENTE el mismo vocabulario de tipo_evento que el parser
 * anterior ('gol', 'gol_en_propia', 'tarjeta_amarilla', 'tarjeta_roja',
 * 'sustitucion') — nada que ya lea eventos_partido debería notar la diferencia.
 *
 * Tipos de evento de LaLiga cubiertos, todos confirmados contra partidos
 * reales: goal(1)=gol, goal(2)=Penalty→gol, goal(3)=Own→gol_en_propia (con
 * inversión de equipo), booking(10)=amarilla, booking(11)=Second Yellow→2
 * eventos (amarilla+roja), booking(12)=roja, substitution(13/14)=sustitucion.
 * 'var' se ignora (no se guardaba tampoco con el interpretador manual).
 */
class ImportadorPartidoDetalle
{
    /** @var array<int,string> avisos de cosas que no se pudieron emparejar o reconocer, para revisar a mano */
    private array $avisos = [];

    public function importar(array $partido, int $idTemporada): array
    {
        $this->avisos = [];

        $equipoLocal = $this->buscarEquipo($partido['equipo_local']);
        $equipoVisitante = $this->buscarEquipo($partido['equipo_visitante']);

        if (! $equipoLocal || ! $equipoVisitante) {
            $this->avisos[] = "No se pudo emparejar el equipo local o visitante: '{$partido['equipo_local']}' / '{$partido['equipo_visitante']}'";
            return ['ok' => false, 'avisos' => $this->avisos];
        }

        $calendarioPartido = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('jornada', $partido['jornada'])
            ->where('id_equipo_local', $equipoLocal->id)
            ->where('id_equipo_visitante', $equipoVisitante->id)
            ->first();

        if (! $calendarioPartido) {
            $this->avisos[] = "No existe en el calendario: jornada {$partido['jornada']}, {$partido['equipo_local']} vs {$partido['equipo_visitante']}";
            return ['ok' => false, 'avisos' => $this->avisos];
        }

        // Qué id numérico de LaLiga corresponde a cada equipo nuestro — para poder
        // saber a quién pertenece cada evento del minuto a minuto.
        $idLaligaPorEquipo = [
            $partido['id_laliga_local'] => $equipoLocal->id,
            $partido['id_laliga_visitante'] => $equipoVisitante->id,
        ];

        $this->importarAlineaciones($calendarioPartido, $equipoLocal, $equipoVisitante, $partido);
        $this->importarEstadisticas($calendarioPartido, $equipoLocal, $equipoVisitante, $partido);
        $this->importarEventos($calendarioPartido, $idLaligaPorEquipo, $partido['events'] ?? []);

        return ['ok' => true, 'avisos' => $this->avisos, 'id_partido' => $calendarioPartido->id];
    }

    // --- ALINEACIONES ---

    private function importarAlineaciones(CalendarioPartido $partido, Equipo $local, Equipo $visitante, array $datos): void
    {
        $mapa = ['home' => [$local, $datos['formacion_local']], 'away' => [$visitante, $datos['formacion_visitante']]];

        foreach ($mapa as $lado => [$equipo, $formacion]) {
            $lineup = $datos['lineups'][$lado] ?? null;
            if (! $lineup) continue;

            // Detecta colisiones: 2 personas distintas del mismo equipo emparejadas
            // al mismo id_jugador. Sin esto, la segunda sobrescribía a la primera
            // en updateOrCreate() sin ningún aviso — así fue como desapareció
            // Ibrahima Konaté en el derbi Atlético-Real Madrid (J7).
            $idsYaUsadosEsteEquipo = [];

            foreach (['starts' => true, 'subs' => false] as $grupo => $titular) {
                foreach ($lineup[$grupo] ?? [] as $entrada) {
                    $jugador = $this->buscarJugador($entrada['person'], $equipo->id, $partido->horario_estimado);

                    if (! $jugador) {
                        $this->avisos[] = "Jugador sin emparejar en alineación ({$equipo->nombre_corto}): {$entrada['person']['name']}";
                        continue;
                    }

                    if (isset($idsYaUsadosEsteEquipo[$jugador->id])) {
                        $this->avisos[] = "Colisión de emparejamiento en alineación ({$equipo->nombre_corto}): '{$entrada['person']['name']}' y '{$idsYaUsadosEsteEquipo[$jugador->id]}' resolvieron al mismo jugador ({$jugador->nombre} {$jugador->apellidos}) — revisar a mano, uno de los 2 se ha quedado sin guardar.";
                        continue;
                    }
                    $idsYaUsadosEsteEquipo[$jugador->id] = $entrada['person']['name'];

                    AlineacionJugador::updateOrCreate(
                        ['id_partido' => $partido->id, 'id_jugador' => $jugador->id],
                        [
                            'id_equipo' => $equipo->id,
                            'dorsal' => $entrada['shirt_number'] ?? null,
                            'titular' => $titular,
                            // El "position" 1-23 que manda LaLiga — base para poder
                            // dibujar al jugador en su línea correcta (defensa/centro/
                            // ataque) en el campito de la ficha de partido.
                            'posicion_formacion' => $entrada['position'] ?? null,
                            'formacion' => $formacion,
                        ]
                    );
                }
            }
        }
    }

    // --- ESTADÍSTICAS ---

    private function importarEstadisticas(CalendarioPartido $partido, Equipo $local, Equipo $visitante, array $datos): void
    {
        $mapa = ['home' => $local, 'away' => $visitante];

        foreach ($mapa as $lado => $equipo) {
            $s = $datos['stats'][$lado] ?? null;
            if (! $s) continue;

            // LaLiga omite la clave por completo cuando el valor es 0 — ?? 0 en cada campo.
            $remates = $s['total_scoring_att'] ?? 0;
            $goles = $s['goals'] ?? 0;

            EstadisticaPartido::updateOrCreate(
                ['id_partido' => $partido->id, 'id_equipo' => $equipo->id],
                [
                    'posesion' => $s['possession_percentage'] ?? null,
                    'remates' => $remates,
                    'efectividad' => $remates > 0 ? round(($goles / $remates) * 100, 1) : 0,
                    'faltas' => $s['fk_foul_lost'] ?? 0,
                    'tarjetas_amarillas' => $s['total_yel_card'] ?? 0,
                    'tarjetas_rojas' => $s['total_red_card'] ?? 0,
                    'fueras_de_juego' => $s['total_offside'] ?? 0,
                    'corners' => $s['corner_taken'] ?? 0,
                    'penaltis_marcados' => $s['att_pen_goal'] ?? 0,
                    'penaltis_intentados' => $s['penalty_won'] ?? 0,
                ]
            );
        }
    }

    // --- EVENTOS (sustituye al parser de texto) ---

    private function importarEventos(CalendarioPartido $partido, array $idLaligaPorEquipo, array $eventos): void
    {
        // Reemplaza en vez de acumular, igual que ya hacía el flujo manual —
        // pegar/importar el mismo partido 2 veces no debe duplicar eventos.
        EventoPartido::where('id_partido', $partido->id)->delete();

        foreach ($eventos as $evento) {
            $kind = $evento['match_event_kind'] ?? null;
            if (! $kind) continue;

            $idLaligaEquipo = $evento['lineup']['team']['id'] ?? null;
            $idEquipo = $idLaligaPorEquipo[$idLaligaEquipo] ?? null;

            if (! $idEquipo) {
                $this->avisos[] = "Evento con equipo LaLiga desconocido (id {$idLaligaEquipo}), minuto {$evento['clock']}";
                continue;
            }

            $minuto = $this->minutoDesdeClock($evento['clock'] ?? null);

            match ($kind['collection']) {
                'goal' => $this->procesarGol($partido, $idEquipo, $kind, $evento, $minuto, $idLaligaPorEquipo),
                'booking' => $this->procesarTarjeta($partido, $idEquipo, $kind, $evento, $minuto),
                'substitution' => $this->procesarSustitucion($partido, $idEquipo, $evento, $minuto),
                'var' => null, // decisiones de VAR — no se guardan hoy, igual que con el parser de texto
                default => $this->avisos[] = "Tipo de evento no reconocido: '{$kind['collection']}' ({$kind['name']}), minuto {$minuto}",
            };
        }
    }

    private function procesarGol(CalendarioPartido $partido, int $idEquipo, array $kind, array $evento, ?int $minuto, array $idLaligaPorEquipo): void
    {
        // id 1 = Goal normal, id 2 = Penalty (ambos son 'gol' hoy, el parser de
        // texto tampoco distinguía penalti como tipo aparte), id 3 = Own (gol en
        // propia) — confirmado contra un partido real (Espanyol-Elche J7).
        $tipo = match ($kind['id']) {
            1, 2 => 'gol',
            3 => 'gol_en_propia',
            default => null,
        };

        if (! $tipo) {
            $this->avisos[] = "Gol de tipo desconocido ('{$kind['name']}', id {$kind['id']}) en el minuto {$minuto} — revisar a mano.";
            return;
        }

        $jugador = $this->buscarJugador($evento['lineup']['person'], $idEquipo, $partido->horario_estimado);
        if (! $jugador) {
            $this->avisos[] = "Goleador sin emparejar: {$evento['lineup']['person']['name']} (minuto {$minuto})";
            return;
        }

        // En propia puerta: el jugador es del equipo indicado, pero el gol cuenta
        // para el marcador del equipo CONTRARIO — invertimos id_equipo, igual que
        // ya hace el interpretador manual (EventoPartidoAdminController).
        $idEquipoDelEvento = $idEquipo;
        if ($tipo === 'gol_en_propia') {
            $otro = array_values(array_diff($idLaligaPorEquipo, [$idEquipo]));
            $idEquipoDelEvento = $otro[0] ?? $idEquipo;
        }

        EventoPartido::create([
            'id_partido' => $partido->id, 'id_jugador' => $jugador->id, 'id_equipo' => $idEquipoDelEvento,
            'minuto' => $minuto, 'tipo_evento' => $tipo, 'id_jugador_relacionado' => null,
        ]);
    }

    private function procesarTarjeta(CalendarioPartido $partido, int $idEquipo, array $kind, array $evento, ?int $minuto): void
    {
        $jugador = $this->buscarJugador($evento['lineup']['person'], $idEquipo, $partido->horario_estimado);
        if (! $jugador) {
            $this->avisos[] = "Jugador amonestado sin emparejar: {$evento['lineup']['person']['name']} (minuto {$minuto})";
            return;
        }

        // id 11 = Second Yellow — confirmada contra un partido real (Valencia-Real
        // Sociedad J7). Se guarda como 2 eventos (amarilla + roja), igual que el
        // interpretador manual acababa produciendo 2 líneas separadas de texto.
        $tipos = match ($kind['id']) {
            10 => ['tarjeta_amarilla'],
            12 => ['tarjeta_roja'],
            11 => ['tarjeta_amarilla', 'tarjeta_roja'],
            default => null,
        };

        if (! $tipos) {
            $this->avisos[] = "Tarjeta de tipo desconocido ('{$kind['name']}', id {$kind['id']}) en el minuto {$minuto} — revisar a mano.";
            return;
        }

        foreach ($tipos as $tipo) {
            EventoPartido::create([
                'id_partido' => $partido->id, 'id_jugador' => $jugador->id, 'id_equipo' => $idEquipo,
                'minuto' => $minuto, 'tipo_evento' => $tipo, 'id_jugador_relacionado' => null,
            ]);
        }
    }

    private function procesarSustitucion(CalendarioPartido $partido, int $idEquipo, array $evento, ?int $minuto): void
    {
        $entra = $this->buscarJugador($evento['lineup']['person'] ?? null, $idEquipo, $partido->horario_estimado);
        $sale = $this->buscarJugador($evento['lineup_off']['person'] ?? null, $idEquipo, $partido->horario_estimado);

        if (! $entra || ! $sale) {
            $nombreEntra = $evento['lineup']['person']['name'] ?? '?';
            $nombreSale = $evento['lineup_off']['person']['name'] ?? '?';
            $this->avisos[] = "Sustitución sin emparejar del todo: entra {$nombreEntra}, sale {$nombreSale} (minuto {$minuto})";
            return;
        }

        // Tactical o Injury — ambas son 'sustitucion' hoy, sin subtipo en el esquema actual.
        EventoPartido::create([
            'id_partido' => $partido->id, 'id_jugador' => $entra->id, 'id_equipo' => $idEquipo,
            'minuto' => $minuto, 'tipo_evento' => 'sustitucion', 'id_jugador_relacionado' => $sale->id,
        ]);
    }

    // --- Ayudantes de emparejamiento y formato ---

    /** "90+7" -> 90, "52" -> 52 — mismo truncamiento que ya hacía el parser de texto al insertar en una columna int. */
    private function minutoDesdeClock(?string $clock): ?int
    {
        if (! $clock) return null;
        return (int) explode('+', $clock)[0];
    }

    private function normalizar(string $texto): string
    {
        $texto = Str::ascii($texto); // quita acentos
        return mb_strtolower(trim($texto));
    }

    private function buscarEquipo(string $nombre): ?Equipo
    {
        $normalizado = $this->normalizar($nombre);

        return Equipo::get()->first(function ($e) use ($normalizado) {
            return $this->normalizar($e->nombre) === $normalizado
                || $this->normalizar($e->nombre_corto ?? '') === $normalizado
                || $this->normalizar($e->apodo ?? '') === $normalizado
                || str_contains($this->normalizar($e->nombre), $normalizado)
                || str_contains($normalizado, $this->normalizar($e->nombre_corto ?? '###'));
        });
    }

    /**
     * Misma lógica de emparejamiento que ya usa `EventoPartidoAdminController`
     * para el interpretador manual — reutilizada tal cual, en vez de mantener
     * una segunda versión propia que pueda divergir con el tiempo (y que, de
     * hecho, ya se demostró más floja: sin el nivel de similitud por texto).
     */
    private function poolJugadoresDeEquipoEnFecha(int $idEquipo, \Illuminate\Support\Carbon $fecha)
    {
        return Jugador::whereHas('plantillasTemporada', function ($q) use ($idEquipo, $fecha) {
            $q->where('id_equipo', $idEquipo)
                ->where(function ($q2) use ($fecha) {
                    $q2->whereNull('fecha_incorporacion')->orWhere('fecha_incorporacion', '<=', $fecha);
                })
                ->where(function ($q2) use ($fecha) {
                    $q2->whereNull('fecha_salida')->orWhere('fecha_salida', '>=', $fecha);
                });
        })->get(['id', 'nombre', 'apellidos', 'nombre_camiseta']);
    }

    private function buscarJugador(?array $person, int $idEquipo, \Illuminate\Support\Carbon $fechaPartido): ?Jugador
    {
        if (! $person) return null;

        $pool = $this->poolJugadoresDeEquipoEnFecha($idEquipo, $fechaPartido);

        // Probamos primero el apodo y luego el nombre completo, con la misma
        // cascada de 3 niveles (exacto → contiene apellido → similitud 65%).
        $candidatosTexto = array_filter([
            $person['nickname'] ?? null,
            trim(($person['firstname'] ?? '').' '.($person['lastname'] ?? '')),
        ]);

        foreach ($candidatosTexto as $texto) {
            $id = $this->buscarPorTexto($texto, $pool);
            if ($id) return $pool->firstWhere('id', $id);
        }

        return null;
    }

    private function buscarPorTexto(string $textoParseado, $pool): ?int
    {
        $normalizado = Str::of($textoParseado)->lower()->ascii()->toString();

        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            $nombreCamiseta = Str::of($jugador->nombre_camiseta ?? '')->lower()->ascii()->toString();

            if ($nombreCompleto === $normalizado || $nombreCamiseta === $normalizado) {
                return $jugador->id;
            }
        }

        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            $apellido = Str::of($jugador->apellidos ?? '')->lower()->ascii()->toString();

            if ($apellido && (Str::contains($nombreCompleto, $normalizado) || Str::contains($normalizado, $apellido))) {
                return $jugador->id;
            }
        }

        $mejorId = null;
        $mejorPorcentaje = 0.0;

        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            similar_text($normalizado, $nombreCompleto, $porcentaje);

            if ($porcentaje > $mejorPorcentaje) {
                $mejorPorcentaje = $porcentaje;
                $mejorId = $jugador->id;
            }
        }

        return $mejorPorcentaje >= 65 ? $mejorId : null;
    }
}