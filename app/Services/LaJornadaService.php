<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "La jornada en tu liga" (/jornadas/{n}/liga): qué ha puesto cada uno.
 *
 * Regla única: SE VE LO QUE YA NO SE PUEDE CAMBIAR.
 *  - Pronósticos de un partido: cuando ese partido ha empezado, o cuando la
 *    jornada está bloqueada (salvo un aplazado, que espera a jugarse).
 *  - Goleadores elegidos: cuando la jornada está bloqueada (es cuando dejan de
 *    poder cambiarse, igual que en GoleadoresController::store).
 *  - Cartas: las jugadas PARA esta jornada, cuando la jornada está bloqueada
 *    (ya no se pueden jugar ni contestar). El Amigo Invisible sigue siendo
 *    anónimo, igual que en Mis Cartas.
 * Antes de eso solo se dan recuentos ("6 de 9 ya han pronosticado").
 *
 * Solo lee.
 */
class LaJornadaService
{
    private const CARTA_ANONIMA = 'FAL-COM-INVISIBLE';

    private const TIPOS_DE_PRONOSTICO = ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2', 'Fallo'];

    /** Con cuántos pronósticos en un partido tiene gracia decir quién va "contra todos". */
    private const MINIMO_PARA_CONTRA_TODOS = 4;

    public function __construct(private RastroCartasService $rastro, private ResumenJornadaService $resumen) {}

    public function resumen(Liga $liga, int $jornada, User $yo): array
    {
        $bloqueada = CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada);

        $miembros = $liga->usuarios()->get()->keyBy('id');

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->orderBy('id')
            ->get();

        $conCartas = $liga->tipo === 'ConExtras';

        return [
            'jornada' => $jornada,
            'bloqueada' => $bloqueada,
            'miembros' => $miembros->count(),
            'con_cartas' => $conCartas,
            'partidos' => $this->partidos($liga, $partidos, $bloqueada, $yo),
            'goleadores' => $this->goleadores($liga, $jornada, $partidos, $bloqueada, $yo),
            'cartas' => $conCartas ? $this->cartas($liga, $jornada, $bloqueada, $yo) : null,
            // Solo con la jornada cerrada; si no, null y la pestaña no sale.
            'resumen' => $this->resumen->resumen($liga, $jornada, $yo),
        ];
    }

    // ------------------------------------------------------------------
    // Pronósticos

    public function partidoVisible(CalendarioPartido $partido, bool $jornadaBloqueada): bool
    {
        if (in_array($partido->estado, ['En juego', 'Jugado'], true)) {
            return true;
        }

        if ($partido->estado === 'Aplazado') {
            return false;
        }

        if ($partido->horario_estimado && $partido->horario_estimado->lte(now())) {
            return true;
        }

        return $jornadaBloqueada;
    }

    private function partidos(Liga $liga, Collection $partidos, bool $bloqueada, User $yo): array
    {
        $ids = $partidos->pluck('id');

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->whereIn('id_partido', $ids)
            ->with('usuario')
            ->get()
            ->groupBy('id_partido');

        $puntos = EventoPuntos::where('id_liga', $liga->id)
            ->whereIn('id_partido', $ids)
            ->whereIn('tipo_evento', self::TIPOS_DE_PRONOSTICO)
            ->get()
            ->keyBy(fn ($e) => $e->id_partido.'-'.$e->id_usuario);

        return $partidos->map(function (CalendarioPartido $partido) use ($pronosticos, $puntos, $bloqueada, $yo) {
            $deEste = $pronosticos->get($partido->id, collect());
            $mio = $deEste->firstWhere('id_usuario', $yo->id);
            $visible = $this->partidoVisible($partido, $bloqueada);

            $fila = [
                'id' => $partido->id,
                'local' => $this->equipo($partido->equipoLocal),
                'visitante' => $this->equipo($partido->equipoVisitante),
                'estado' => $partido->estado,
                'horario' => $partido->horario_estimado?->toIso8601String(),
                'goles_casa' => $partido->goles_casa,
                'goles_fuera' => $partido->goles_fuera,
                'visible' => $visible,
                'total_pronosticos' => $deEste->count(),
                'mi_pronostico' => $mio ? $this->marcador($mio) : null,
                'reparto' => null,
                'mas_repetido' => null,
                'contra_todos' => [],
                'pronosticos' => [],
            ];

            if (! $visible || $deEste->isEmpty()) {
                return $fila;
            }

            $filas = $deEste->map(fn (Pronostico $p) => [
                'id_usuario' => $p->id_usuario,
                'usuario' => $this->nombre($p->usuario),
                'avatar_url' => $this->avatar($p->usuario),
                'pronostico' => $this->marcador($p),
                'signo' => $this->signo($p->goles_local_predicho, $p->goles_visitante_predicho),
                'puntos' => $puntos->get($partido->id.'-'.$p->id_usuario)?->puntos,
                'tipo_evento' => $puntos->get($partido->id.'-'.$p->id_usuario)?->tipo_evento,
                'soy_yo' => $p->id_usuario === $yo->id,
            ]);

            $reparto = ['Local' => 0, 'Empate' => 0, 'Visitante' => 0];
            foreach ($filas as $f) {
                $reparto[$f['signo']]++;
            }

            $porMarcador = $filas->countBy('pronostico')->sortDesc();
            $masRepetido = $porMarcador->keys()->first();

            $contraTodos = [];
            if ($filas->count() >= self::MINIMO_PARA_CONTRA_TODOS) {
                foreach ($reparto as $signo => $veces) {
                    if ($veces === 1) {
                        $solo = $filas->firstWhere('signo', $signo);
                        $contraTodos[] = ['usuario' => $solo['usuario'], 'signo' => $signo, 'soy_yo' => $solo['soy_yo']];
                    }
                }
            }

            $fila['reparto'] = $reparto;
            $fila['mas_repetido'] = $porMarcador->first() > 1 ? ['marcador' => $masRepetido, 'veces' => $porMarcador->first()] : null;
            $fila['contra_todos'] = $contraTodos;
            $fila['pronosticos'] = $filas
                ->sortBy([['puntos', 'desc'], ['usuario', 'asc']])
                ->values()
                ->all();

            return $fila;
        })->values()->all();
    }

    // ------------------------------------------------------------------
    // Goleadores

    private function goleadores(Liga $liga, int $jornada, Collection $partidos, bool $bloqueada, User $yo): array
    {
        $selecciones = GoleadorJornada::where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->with('jugador')
            ->get();

        $base = [
            'visible' => $bloqueada,
            'usuarios_con_seleccion' => $selecciones->pluck('id_usuario')->unique()->count(),
            'mis_elegidos' => $selecciones->where('id_usuario', $yo->id)->count(),
            'jugadores' => [],
        ];

        if (! $bloqueada || $selecciones->isEmpty()) {
            return $base;
        }

        $usuarios = User::whereIn('id', $selecciones->pluck('id_usuario')->unique())->get()->keyBy('id');

        // Goles de esta jornada (sin los de propia puerta, igual que el resto de la app).
        $goles = DB::table('eventos_partido')
            ->whereIn('id_partido', $partidos->pluck('id'))
            ->where('tipo_evento', 'gol')
            ->whereNotNull('id_jugador')
            ->selectRaw('id_jugador, COUNT(*) as goles')
            ->groupBy('id_jugador')
            ->pluck('goles', 'id_jugador');

        $base['jugadores'] = $selecciones
            ->groupBy('id_jugador')
            ->map(function (Collection $grupo, $idJugador) use ($usuarios, $goles, $yo) {
                $jugador = $grupo->first()->jugador;

                return [
                    'id_jugador' => (int) $idJugador,
                    'nombre' => $jugador ? ($jugador->nombre_camiseta ?: trim("{$jugador->nombre} {$jugador->apellidos}")) : 'Jugador desconocido',
                    'foto_url' => $jugador?->foto_url,
                    'goles' => (int) ($goles[$idJugador] ?? 0),
                    'elegido_por' => $grupo->map(fn ($g) => [
                        'id_usuario' => $g->id_usuario,
                        'usuario' => $this->nombre($usuarios->get($g->id_usuario)),
                        'avatar_url' => $this->avatar($usuarios->get($g->id_usuario)),
                        'soy_yo' => $g->id_usuario === $yo->id,
                    ])->sortBy('usuario')->values()->all(),
                ];
            })
            ->sortBy([
                fn ($a, $b) => count($b['elegido_por']) <=> count($a['elegido_por']),
                fn ($a, $b) => $b['goles'] <=> $a['goles'],
                fn ($a, $b) => strcmp($a['nombre'], $b['nombre']),
            ])
            ->values()
            ->all();

        return $base;
    }

    // ------------------------------------------------------------------
    // Cartas

    private function cartas(Liga $liga, int $jornada, bool $bloqueada, User $yo): array
    {
        $jugadas = CartaUsuario::where('id_liga', $liga->id)
            ->where('jornada_efecto', $jornada)
            ->whereNotNull('jugada_en')
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->with(['tipoCarta.categoria', 'usuario', 'usuarioObjetivo', 'partido.equipoLocal', 'partido.equipoVisitante'])
            ->orderBy('jugada_en')
            ->orderBy('id')
            ->get();

        $base = [
            'visible' => $bloqueada,
            'total' => $jugadas->count(),
            'lista' => [],
        ];

        if (! $bloqueada) {
            return $base;
        }

        $base['lista'] = $jugadas->map(function (CartaUsuario $carta) use ($yo) {
            $tipo = $carta->tipoCarta;
            $codigo = (string) $tipo?->codigo_efecto;
            $forma = $this->rastro->formaDe($codigo, $tipo?->categoria?->nombre);
            $esFalta = $forma === 'falta';
            $anonima = $codigo === self::CARTA_ANONIMA;
            $mia = $carta->id_usuario === $yo->id;
            $paraSiMismo = $esFalta && $carta->id_usuario_objetivo === $carta->id_usuario;

            $quien = $anonima
                ? ($mia ? 'Tú (en secreto)' : 'Alguien…')
                : $this->nombre($carta->usuario);

            return [
                'id' => $carta->id,
                'nombre' => $tipo?->nombre ?? 'Carta',
                'rareza' => $tipo?->rareza,
                'es_falta' => $esFalta,
                'para_si_mismo' => $paraSiMismo,
                'quien' => $quien,
                'quien_avatar' => $anonima ? null : $this->avatar($carta->usuario),
                'soy_yo' => $mia && ! $anonima,
                'objetivo' => $esFalta && ! $paraSiMismo ? $this->nombre($carta->usuarioObjetivo) : null,
                'objetivo_soy_yo' => $esFalta && ! $paraSiMismo && $carta->id_usuario_objetivo === $yo->id,
                'sobre' => $esFalta ? null : ($carta->partido ? $this->etiquetaPartido($carta->partido) : 'Toda la jornada'),
                // El mensaje del Silbato solo lo leen quien lo recibe y quien lo mandó.
                'mensaje' => $esFalta && ! $paraSiMismo && ($tipo?->codigo_efecto !== 'FAL-COM-SILBATO' || $mia || $carta->id_usuario_objetivo === $yo->id)
                    ? $carta->mensaje_falta
                    : null,
                'estado' => match ($carta->estado) {
                    'resuelta_cumplida' => 'cumplida',
                    'resuelta_no_cumplida' => 'sin_efecto',
                    default => 'pendiente',
                },
                'puntos' => (int) $carta->puntos_generados,
            ];
        })->values()->all();

        return $base;
    }

    // ------------------------------------------------------------------

    private function equipo($equipo): array
    {
        return [
            'nombre' => $equipo?->nombre_corto ?: ($equipo?->nombre ?? '?'),
            'escudo_url' => $equipo?->escudo_url,
        ];
    }

    private function etiquetaPartido(CalendarioPartido $partido): string
    {
        return $this->equipo($partido->equipoLocal)['nombre'].' - '.$this->equipo($partido->equipoVisitante)['nombre'];
    }

    private function marcador(Pronostico $p): string
    {
        return "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}";
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
