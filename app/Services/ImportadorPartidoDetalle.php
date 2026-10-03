<?php

namespace App\Services;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\EstadisticaPartido;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sustituye a ParserComentariosPartido: en vez de interpretar el texto pegado a
 * mano de "Comentarios", procesa el JSON estructurado que trae la propia página
 * de LaLiga (bloque __NEXT_DATA__).
 *
 * Produce EXACTAMENTE el mismo vocabulario de tipo_evento que el parser
 * anterior ('gol', 'gol_en_propia', 'tarjeta_amarilla', 'tarjeta_roja',
 * 'sustitucion') — nada que ya lea eventos_partido debería notar la diferencia.
 *
 * Tipos de evento de LaLiga cubiertos, todos confirmados contra partidos
 * reales: goal(1)=gol, goal(2)=Penalty→gol, goal(3)=Own→gol_en_propia (con
 * inversión de equipo), booking(10)=amarilla, booking(11)=Second Yellow→2
 * eventos (amarilla+roja), booking(12)=roja, substitution(13/14)=sustitucion.
 * 'var' y 'missedPenalty' se ignoran (no hay dónde guardarlos hoy).
 *
 * CÓMO SE EMPAREJA A CADA JUGADOR (regla general: ante la duda, NO se asigna
 * a nadie y se avisa — un gol mal asignado son puntos mal dados).
 *
 * Solo se busca dentro de la plantilla de ese equipo, esa temporada y esa
 * fecha. Toda la alineación de un equipo se resuelve junta, de la pista más
 * fuerte a la más floja, y un jugador ya asignado no puede asignarse a otro:
 *
 *   1. completo  — el nombre de LaLiga es idéntico a "nombre apellidos".
 *   2. camiseta  — el nombre o apodo de LaLiga es idéntico al nombre de camiseta.
 *   3. dorsal    — mismo dorsal que en plantilla_temporada y, además, alguna
 *                  palabra del nombre o apellido en común.
 *   4. palabras  — el apellido aparece como PALABRA ENTERA (nunca como trozo:
 *                  "Brahim" no es "Ibrahima") y el nombre de pila no choca
 *                  ("Hugo Martín" no es "Andrés Martín").
 *   5. similitud — texto casi idéntico (erratas), con un mejor candidato claro.
 *
 * En cada nivel solo vale si queda UN candidato; si quedan varios, desempata
 * el dorsal, y si no desempata, se deja sin asignar.
 *
 * Los goles, tarjetas y cambios NO vuelven a adivinar: usan el jugador que ya
 * se emparejó en la alineación de ese mismo partido. Solo si el partido llega
 * sin alineación se busca por nombre, con estas mismas reglas.
 */
class ImportadorPartidoDetalle
{
    /** Partículas de apellido que no sirven como pista (las de 1-2 letras ya se descartan por longitud). */
    private const PALABRAS_VACIAS = ['del', 'las', 'los', 'van', 'von', 'der', 'den', 'dos', 'das'];

    private const NIVELES = ['completo', 'camiseta', 'dorsal', 'palabras', 'similitud'];

    private const SIMILITUD_CON_DORSAL = 70.0;
    private const SIMILITUD_SIN_DORSAL = 85.0;
    private const MARGEN_SIMILITUD = 10.0;

    /** @var array<int,string> avisos de cosas que no se pudieron emparejar o reconocer, para revisar a mano */
    private array $avisos = [];

    /** @var array<int,array<int,array>> [id_equipo][id_jugador] => ficha — la plantilla de cada equipo el día del partido */
    private array $plantillas = [];

    /** @var array<int,array<string,?int>> [id_equipo][nombre de LaLiga] => id_jugador (null = estaba en la alineación pero no se pudo emparejar) */
    private array $jugadorPorPersona = [];

    /** @var array<int,array<string,bool>> [id_equipo][nombre de LaLiga] => true — entrenadores, para no avisar si los amonestan */
    private array $entrenadores = [];

    /** @var array<int,array>|null fichas de TODOS los jugadores — solo para afinar el texto de un aviso, se carga si hace falta */
    private ?array $todosLosJugadores = null;

    public function importar(array $partido, int $idTemporada): array
    {
        $this->avisos = [];
        $this->plantillas = [];
        $this->jugadorPorPersona = [];
        $this->entrenadores = [];
        $this->todosLosJugadores = null;

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

        $fecha = $calendarioPartido->horario_estimado ?? now();
        $this->plantillas[$equipoLocal->id] = $this->cargarPlantilla($equipoLocal->id, $idTemporada, $fecha);
        $this->plantillas[$equipoVisitante->id] = $this->cargarPlantilla($equipoVisitante->id, $idTemporada, $fecha);

        // Todo o nada: si algo falla a medias, no se queda el partido con los
        // eventos borrados y sin volver a crear.
        DB::transaction(function () use ($calendarioPartido, $equipoLocal, $equipoVisitante, $partido, $idLaligaPorEquipo) {
            $this->importarAlineaciones($calendarioPartido, $equipoLocal, $equipoVisitante, $partido);
            $this->importarEstadisticas($calendarioPartido, $equipoLocal, $equipoVisitante, $partido);
            $this->importarEventos($calendarioPartido, $idLaligaPorEquipo, $partido['events'] ?? []);
        });

        return ['ok' => true, 'avisos' => $this->avisos, 'id_partido' => $calendarioPartido->id];
    }

    // --- ALINEACIONES ---

    private function importarAlineaciones(CalendarioPartido $partido, Equipo $local, Equipo $visitante, array $datos): void
    {
        $mapa = ['home' => [$local, $datos['formacion_local'] ?? ''], 'away' => [$visitante, $datos['formacion_visitante'] ?? '']];

        foreach ($mapa as $lado => [$equipo, $formacion]) {
            $lineup = $datos['lineups'][$lado] ?? null;
            if (! $lineup) continue;

            foreach ($lineup['manager'] ?? [] as $entrenador) {
                $clave = $this->clavePersona($entrenador['person'] ?? null);
                if ($clave !== '') {
                    $this->entrenadores[$equipo->id][$clave] = true;
                }
            }

            $entradas = [];
            foreach (['starts' => true, 'subs' => false] as $grupo => $titular) {
                foreach ($lineup[$grupo] ?? [] as $entrada) {
                    if (empty($entrada['person'])) continue;

                    $dorsal = isset($entrada['shirt_number']) ? (int) $entrada['shirt_number'] : null;
                    $entradas[] = [
                        'datos' => $entrada,
                        'titular' => $titular,
                        'persona' => $this->fichaDePersona($entrada['person'], $dorsal),
                    ];
                }
            }

            // LaLiga aún no ha publicado la alineación de este equipo: no se toca
            // lo que ya hubiera guardado de una importación anterior.
            if (! $entradas) continue;

            $plantilla = $this->plantillas[$equipo->id] ?? [];
            $asignados = $this->emparejarGrupo(array_column($entradas, 'persona'), $plantilla);
            $idsAsignados = array_flip(array_filter($asignados));

            // Reemplaza en vez de acumular, igual que los eventos: si una
            // importación anterior asignó mal a alguien, esa fila no debe quedarse.
            AlineacionJugador::where('id_partido', $partido->id)->where('id_equipo', $equipo->id)->delete();

            foreach ($entradas as $i => $entrada) {
                $idJugador = $asignados[$i] ?? null;
                $persona = $entrada['persona'];

                // Se apunta para que goles, tarjetas y cambios de esta persona usen
                // este mismo resultado. Dos entradas con el mismo nombre: ninguna vale.
                if ($persona['clave'] !== '') {
                    $yaEstaba = array_key_exists($persona['clave'], $this->jugadorPorPersona[$equipo->id] ?? []);
                    $this->jugadorPorPersona[$equipo->id][$persona['clave']] = $yaEstaba ? null : $idJugador;
                }

                if (! $idJugador) {
                    $dorsalTexto = $persona['dorsal'] !== null ? " (dorsal {$persona['dorsal']})" : '';
                    $motivo = $this->motivoSinEmparejar($persona, $plantilla, $idsAsignados, $equipo);
                    $this->avisos[] = "Jugador sin emparejar en alineación ({$equipo->nombre_corto}): {$persona['etiqueta']}{$dorsalTexto} — {$motivo}";
                    continue;
                }

                AlineacionJugador::create([
                    'id_partido' => $partido->id,
                    'id_jugador' => $idJugador,
                    'id_equipo' => $equipo->id,
                    'dorsal' => $entrada['datos']['shirt_number'] ?? null,
                    'titular' => $entrada['titular'],
                    // El "position" 1-23 que manda LaLiga — base para poder
                    // dibujar al jugador en su línea correcta (defensa/centro/
                    // ataque) en el campito de la ficha de partido.
                    'posicion_formacion' => $entrada['datos']['position'] ?? null,
                    'formacion' => $formacion,
                ]);
            }
        }
    }

    /** Texto del aviso: distingue "podría ser fulano", "existe pero no en esta plantilla" y "no está dado de alta". */
    private function motivoSinEmparejar(array $persona, array $plantilla, array $idsAsignados, Equipo $equipo): string
    {
        $posibles = [];
        foreach ($plantilla as $id => $jugador) {
            if (isset($idsAsignados[$id])) continue;

            $mismoDorsal = $persona['dorsal'] !== null && $jugador['dorsal'] === $persona['dorsal'];
            if ($mismoDorsal || $this->apellidoCoincide($persona, $jugador)) {
                $posibles[] = $jugador['etiqueta'].($jugador['dorsal'] !== null ? " (dorsal {$jugador['dorsal']})" : ' (sin dorsal)');
            }
        }

        if ($posibles) {
            $lista = implode(' o ', array_slice($posibles, 0, 3));
            return "dudoso: podría ser {$lista}, pero no hay datos suficientes para asegurarlo. Si es él, rellenar su dorsal o su nombre de camiseta lo resuelve.";
        }

        foreach ($this->todosLosJugadores() as $jugador) {
            $esElMismo = ($jugador['completo'] !== '' && in_array($jugador['completo'], $persona['textos'], true))
                || ($jugador['camiseta'] !== '' && in_array($jugador['camiseta'], $persona['textos'], true)
                    && $this->apellidosCompatibles($persona, $jugador));

            if ($esElMismo) {
                return "existe en la base de datos ({$jugador['etiqueta']}, id {$jugador['id']}) pero no figura en la plantilla de {$equipo->nombre_corto} en la fecha del partido — revisar plantilla_temporada.";
            }
        }

        return 'no está dado de alta en la base de datos (o figura con un nombre muy distinto).';
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
                $reloj = $evento['clock'] ?? '?';
                $this->avisos[] = "Evento con equipo LaLiga desconocido (id {$idLaligaEquipo}), minuto {$reloj}";
                continue;
            }

            $minuto = $this->minutoDesdeClock($evento['clock'] ?? null);

            match ($kind['collection']) {
                'goal' => $this->procesarGol($partido, $idEquipo, $kind, $evento, $minuto, $idLaligaPorEquipo),
                'booking' => $this->procesarTarjeta($partido, $idEquipo, $kind, $evento, $minuto),
                'substitution' => $this->procesarSustitucion($partido, $idEquipo, $evento, $minuto),
                // Decisiones de VAR y penaltis fallados — no se guardan hoy (el
                // esquema no tiene dónde), así que tampoco merecen aviso.
                'var', 'missedPenalty' => null,
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

        $persona = $evento['lineup']['person'] ?? null;
        $jugador = $this->jugadorDeEvento($persona, $idEquipo);
        if (! $jugador) {
            $nombre = $persona['name'] ?? '?';
            $this->avisos[] = "Goleador sin emparejar: {$nombre} (minuto {$minuto}) — ESTE GOL NO SE HA GUARDADO, añadirlo a mano.";
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
        $persona = $evento['lineup']['person'] ?? null;

        // Tarjeta a un entrenador: no es un jugador, no hay nada que guardar ni que avisar.
        if ($this->esEntrenador($persona, $idEquipo)) return;

        $jugador = $this->jugadorDeEvento($persona, $idEquipo);
        if (! $jugador) {
            $nombre = $persona['name'] ?? '?';
            $this->avisos[] = "Jugador amonestado sin emparejar: {$nombre} (minuto {$minuto})";
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
        $entra = $this->jugadorDeEvento($evento['lineup']['person'] ?? null, $idEquipo);
        $sale = $this->jugadorDeEvento($evento['lineup_off']['person'] ?? null, $idEquipo);

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

    /**
     * El jugador de un gol, tarjeta o cambio. Si esa persona venía en la
     * alineación de este partido, se usa lo que ya se decidió allí (para bien o
     * para mal: si allí no se pudo emparejar, aquí tampoco se inventa). Solo si
     * no venía en la alineación se busca por nombre, con las mismas reglas.
     */
    private function jugadorDeEvento(?array $person, int $idEquipo): ?Jugador
    {
        if (! $person) return null;

        $clave = $this->clavePersona($person);
        $plantilla = $this->plantillas[$idEquipo] ?? [];

        if ($clave === '' || ! array_key_exists($clave, $this->jugadorPorPersona[$idEquipo] ?? [])) {
            $asignados = $this->emparejarGrupo([$this->fichaDePersona($person, null)], $plantilla);
            $this->jugadorPorPersona[$idEquipo][$clave] = $asignados[0] ?? null;
        }

        $idJugador = $this->jugadorPorPersona[$idEquipo][$clave] ?? null;

        return $idJugador ? ($plantilla[$idJugador]['modelo'] ?? null) : null;
    }

    private function esEntrenador(?array $person, int $idEquipo): bool
    {
        $clave = $this->clavePersona($person);

        return $clave !== '' && isset($this->entrenadores[$idEquipo][$clave]);
    }

    // --- Emparejamiento de jugadores ---

    /** Plantilla de un equipo el día del partido, como fichas ya normalizadas y con su dorsal. */
    private function cargarPlantilla(int $idEquipo, int $idTemporada, $fecha): array
    {
        $dia = $fecha->toDateString();

        $filas = PlantillaTemporada::with('jugador')
            ->where('id_equipo', $idEquipo)
            ->where('id_temporada', $idTemporada)
            ->where(function ($q) use ($dia) {
                $q->whereNull('fecha_incorporacion')->orWhereDate('fecha_incorporacion', '<=', $dia);
            })
            ->where(function ($q) use ($dia) {
                $q->whereNull('fecha_salida')->orWhereDate('fecha_salida', '>=', $dia);
            })
            ->get();

        $plantilla = [];
        foreach ($filas as $fila) {
            $jugador = $fila->jugador;
            if (! $jugador) continue;

            $dorsal = $fila->dorsal !== null ? (int) $fila->dorsal : null;

            if (isset($plantilla[$jugador->id])) {
                // Dos fichas del mismo jugador en el mismo equipo: nos quedamos con el dorsal que haya.
                $plantilla[$jugador->id]['dorsal'] ??= $dorsal;
                continue;
            }

            $plantilla[$jugador->id] = $this->fichaDesdeDatos($jugador->id, $jugador->nombre, $jugador->apellidos, $jugador->nombre_camiseta, $dorsal, $jugador);
        }

        return $plantilla;
    }

    private function todosLosJugadores(): array
    {
        if ($this->todosLosJugadores === null) {
            $this->todosLosJugadores = [];
            foreach (Jugador::get(['id', 'nombre', 'apellidos', 'nombre_camiseta']) as $jugador) {
                $this->todosLosJugadores[] = $this->fichaDesdeDatos($jugador->id, $jugador->nombre, $jugador->apellidos, $jugador->nombre_camiseta, null, $jugador);
            }
        }

        return $this->todosLosJugadores;
    }

    /** Ficha de un jugador NUESTRO, con sus textos ya limpios y troceados en palabras. */
    private function fichaDesdeDatos(int $id, ?string $nombre, ?string $apellidos, ?string $camiseta, ?int $dorsal, mixed $modelo = null): array
    {
        $nombreLimpio = $this->limpiar($nombre);
        $apellidosLimpio = $this->limpiar($apellidos);
        $camisetaLimpia = $this->limpiar($camiseta);
        $completo = trim("{$nombreLimpio} {$apellidosLimpio}");

        $palabrasApellidos = $this->palabras($apellidosLimpio);

        return [
            'id' => $id,
            'modelo' => $modelo,
            'etiqueta' => trim(($nombre ?? '').' '.($apellidos ?? '')) ?: ($camiseta ?? "id {$id}"),
            'dorsal' => $dorsal,
            'completo' => $completo,
            'camiseta' => $camisetaLimpia,
            'palabras' => $this->palabras("{$completo} {$camisetaLimpia}"),
            'palabrasApellidos' => $palabrasApellidos,
            // Cómo se le llama de pila: su nombre y lo que haya en la camiseta que no sea apellido.
            'palabrasNombre' => array_values(array_unique(array_merge(
                $this->palabras($nombreLimpio),
                array_diff($this->palabras($camisetaLimpia), $palabrasApellidos)
            ))),
        ];
    }

    /** Ficha de una persona tal como la manda LaLiga (name / nickname / firstname / lastname) y su dorsal en ese partido. */
    private function fichaDePersona(array $person, ?int $dorsal): array
    {
        $nombreCompleto = $this->limpiar($person['name'] ?? null);
        $apodo = $this->limpiar($person['nickname'] ?? null);
        $nombre = $this->limpiar($person['firstname'] ?? null);
        $apellido = $this->limpiar($person['lastname'] ?? null);

        $textos = array_values(array_unique(array_filter([$nombreCompleto, $apodo, trim("{$nombre} {$apellido}")], fn ($t) => $t !== '')));
        $palabrasApellido = $this->palabras($apellido);

        return [
            'etiqueta' => $person['name'] ?? $person['nickname'] ?? '?',
            'clave' => $this->clavePersona($person),
            'dorsal' => $dorsal,
            'textos' => $textos,
            'palabras' => $this->palabras(implode(' ', $textos)),
            'palabrasApellidos' => $palabrasApellido,
            'palabrasNombre' => array_values(array_unique(array_merge(
                $this->palabras($nombre),
                array_diff($this->palabras($apodo), $palabrasApellido)
            ))),
        ];
    }

    /** Con qué nombre se reconoce a la misma persona entre la alineación y los eventos de un partido. */
    private function clavePersona(?array $person): string
    {
        if (! $person) return '';

        $clave = $this->limpiar($person['name'] ?? null);
        if ($clave === '') {
            $clave = $this->limpiar(trim(($person['firstname'] ?? '').' '.($person['lastname'] ?? '')));
        }
        if ($clave === '') {
            $clave = $this->limpiar($person['nickname'] ?? null);
        }

        return $clave;
    }

    /**
     * Resuelve juntas varias personas de LaLiga contra una plantilla.
     *
     * @param  array<int,array>  $personas  fichas de fichaDePersona()
     * @param  array<int,array>  $plantilla  [id_jugador] => ficha de fichaDesdeDatos()
     * @return array<int,?int> misma clave que $personas => id_jugador, o null si no se puede asegurar
     */
    private function emparejarGrupo(array $personas, array $plantilla): array
    {
        $asignados = array_fill_keys(array_keys($personas), null);
        $ocupados = [];

        // Se repite mientras alguien nuevo quede resuelto: al asignar a uno de
        // dos candidatos dudosos (los dos Williams), el otro queda ya sin duda.
        do {
            $huboCambio = false;

            foreach (self::NIVELES as $nivel) {
                foreach ($personas as $i => $persona) {
                    if ($asignados[$i] !== null) continue;

                    $libres = array_diff_key($plantilla, $ocupados);
                    $id = $this->candidatoUnico($nivel, $persona, $libres);

                    if ($id !== null) {
                        $asignados[$i] = $id;
                        $ocupados[$id] = true;
                        $huboCambio = true;
                    }
                }
            }
        } while ($huboCambio);

        return $asignados;
    }

    /** El único jugador libre que encaja con la persona en ese nivel; null si no hay ninguno o no se puede elegir entre varios. */
    private function candidatoUnico(string $nivel, array $persona, array $libres): ?int
    {
        if ($nivel === 'similitud') {
            return $this->mejorPorSimilitud($persona, $libres);
        }

        $candidatos = [];
        foreach ($libres as $id => $jugador) {
            $encaja = match ($nivel) {
                'completo' => $jugador['completo'] !== '' && in_array($jugador['completo'], $persona['textos'], true),
                'camiseta' => $jugador['camiseta'] !== '' && in_array($jugador['camiseta'], $persona['textos'], true)
                    && $this->nombresCompatibles($persona, $jugador) && $this->apellidosCompatibles($persona, $jugador),
                // Mismo equipo + mismo dorsal + alguna palabra del nombre en común: es él,
                // aunque los apellidos no cuadren ("Moriba Kourouma" / "Ilaix Moriba").
                'dorsal' => $persona['dorsal'] !== null && $jugador['dorsal'] === $persona['dorsal']
                    && ($this->compartenPalabra($persona['palabras'], $jugador['palabras']) || $this->similitud($persona, $jugador) >= self::SIMILITUD_CON_DORSAL),
                'palabras' => $this->apellidoCoincide($persona, $jugador)
                    && $this->nombresCompatibles($persona, $jugador) && $this->apellidosCompatibles($persona, $jugador),
                default => false,
            };

            if ($encaja) {
                $candidatos[] = $id;
            }
        }

        if (count($candidatos) === 1) {
            return $candidatos[0];
        }

        // Varios candidatos: solo el dorsal puede desempatar.
        if (count($candidatos) > 1 && $persona['dorsal'] !== null) {
            $conEseDorsal = array_values(array_filter($candidatos, fn ($id) => $libres[$id]['dorsal'] === $persona['dorsal']));
            if (count($conEseDorsal) === 1) {
                return $conEseDorsal[0];
            }
        }

        return null;
    }

    private function mejorPorSimilitud(array $persona, array $libres): ?int
    {
        $mejorId = null;
        $mejor = 0.0;
        $segundo = 0.0;

        foreach ($libres as $id => $jugador) {
            if (! $this->nombresCompatibles($persona, $jugador) || ! $this->apellidosCompatibles($persona, $jugador)) continue;

            $porcentaje = $this->similitud($persona, $jugador);

            if ($porcentaje > $mejor) {
                $segundo = $mejor;
                $mejor = $porcentaje;
                $mejorId = $id;
            } elseif ($porcentaje > $segundo) {
                $segundo = $porcentaje;
            }
        }

        $hayUnMejorClaro = $mejor >= self::SIMILITUD_SIN_DORSAL && ($mejor - $segundo) >= self::MARGEN_SIMILITUD;

        return $hayUnMejorClaro ? $mejorId : null;
    }

    /** Mayor parecido (0-100) entre cualquiera de los nombres de LaLiga y el nombre completo o de camiseta nuestro. */
    private function similitud(array $persona, array $jugador): float
    {
        $mejor = 0.0;

        foreach ($persona['textos'] as $texto) {
            foreach ([$jugador['completo'], $jugador['camiseta']] as $nuestro) {
                if ($nuestro === '') continue;
                similar_text($texto, $nuestro, $porcentaje);
                $mejor = max($mejor, $porcentaje);
            }
        }

        return $mejor;
    }

    /**
     * El apellido coincide como palabras enteras: o todos nuestros apellidos
     * aparecen en el nombre de LaLiga, o todos los de LaLiga aparecen en el nuestro.
     */
    private function apellidoCoincide(array $persona, array $jugador): bool
    {
        return $this->todasContenidas($jugador['palabrasApellidos'], $persona['palabras'])
            || $this->todasContenidas($persona['palabrasApellidos'], $jugador['palabras']);
    }

    /** false solo si los dos tienen nombre de pila y no se parecen en nada ("Hugo" / "Andrés"). */
    private function nombresCompatibles(array $persona, array $jugador): bool
    {
        if (! $persona['palabrasNombre'] || ! $jugador['palabrasNombre']) return true;

        foreach ($persona['palabrasNombre'] as $a) {
            foreach ($jugador['palabrasNombre'] as $b) {
                if ($this->nombresParecidos($a, $b)) return true;
            }
        }

        return false;
    }

    /** false solo si los dos tienen apellido y no comparten ninguno ("García" / "Moro"). */
    private function apellidosCompatibles(array $persona, array $jugador): bool
    {
        if (! $persona['palabrasApellidos'] || ! $jugador['palabrasApellidos']) return true;

        foreach ($persona['palabrasApellidos'] as $a) {
            foreach ($jugador['palabrasApellidos'] as $b) {
                if ($this->casiLaMismaPalabra($a, $b)) return true;
            }
        }

        return false;
    }

    private function compartenPalabra(array $unas, array $otras): bool
    {
        foreach ($unas as $a) {
            foreach ($otras as $b) {
                if ($this->mismaPalabra($a, $b)) return true;
            }
        }

        return false;
    }

    /** Todas las palabras de $buscadas están en $donde (y hay al menos una que buscar). */
    private function todasContenidas(array $buscadas, array $donde): bool
    {
        if (! $buscadas) return false;

        foreach ($buscadas as $buscada) {
            $esta = false;
            foreach ($donde as $palabra) {
                if ($this->mismaPalabra($buscada, $palabra)) {
                    $esta = true;
                    break;
                }
            }
            if (! $esta) return false;
        }

        return true;
    }

    /** Para dar algo por BUENO la palabra tiene que ser idéntica: "hernandez" no es "fernandez". */
    private function mismaPalabra(string $a, string $b): bool
    {
        return $a === $b;
    }

    /**
     * Solo para NO descartar a alguien por una errata ("oyarzabal" / "oiarzabal"):
     * misma inicial y una sola letra de diferencia. Nunca sirve como prueba a favor.
     */
    private function casiLaMismaPalabra(string $a, string $b): bool
    {
        if ($a === $b) return true;

        return $a[0] === $b[0] && strlen($a) >= 5 && strlen($b) >= 5 && levenshtein($a, $b) <= 1;
    }

    /** Nombres de pila que pueden ser el mismo: iguales, uno abrevia al otro ("nico" / "nicolas") o una errata. */
    private function nombresParecidos(string $a, string $b): bool
    {
        if ($a === $b) return true;

        [$corto, $largo] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
        if (strlen($corto) >= 3 && str_starts_with($largo, $corto)) return true;

        return strlen($corto) >= 4 && levenshtein($a, $b) <= 1;
    }

    /** Minúsculas, sin acentos y sin signos: "Louis-Jean" → "louis jean", "O. Sancet" → "o sancet". */
    private function limpiar(?string $texto): string
    {
        $texto = Str::lower(Str::ascii((string) $texto));
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';

        return trim($texto);
    }

    /** Palabras que sirven de pista: 3 letras o más, sin partículas de apellido. */
    private function palabras(string $textoLimpio): array
    {
        if (trim($textoLimpio) === '') return [];

        return array_values(array_unique(array_filter(
            explode(' ', trim($textoLimpio)),
            fn ($palabra) => strlen($palabra) >= 3 && ! in_array($palabra, self::PALABRAS_VACIAS, true)
        )));
    }

    // --- Ayudantes de equipo y formato (sin cambios) ---

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
}
