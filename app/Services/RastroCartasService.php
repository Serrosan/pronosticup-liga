<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\Pronostico;

/**
 * El rastro de una carta (/admin/rastro-cartas): su historia contada en orden
 * — cómo llegó, cuándo se abrió, sobre qué se jugó, qué pasó y por qué.
 *
 * SOLO LEE. No resuelve nada ni toca el motor de cartas: junta lo que el
 * motor ya dejó guardado (estado y puntos de la carta, puntos del pronóstico,
 * eventos del partido) y lo explica. Los "datos" que acompañan a cada carta
 * son hechos sacados de la base de datos, no una segunda versión del cálculo.
 */
class RastroCartasService
{
    private const TIPOS_DE_PRONOSTICO = [
        'AciertoExacto' => 'resultado exacto',
        'AciertoDiferencia' => 'diferencia de goles',
        'Acierto1x2' => 'signo',
        'Fallo' => 'fallo',
    ];

    /**
     * @param string $vista 'jornada' (jugadas para esa jornada) | 'mano' (en la mano ahora)
     */
    public function cartas(Liga $liga, string $vista, ?int $jornada): array
    {
        $consulta = CartaUsuario::where('id_liga', $liga->id)
            ->with(['tipoCarta.categoria', 'usuario', 'usuarioObjetivo', 'partido.equipoLocal', 'partido.equipoVisitante']);

        if ($vista === 'mano') {
            $consulta->where('estado', 'en_mano')->orderBy('id_usuario')->orderBy('obtenida_en');
        } else {
            $consulta->where('jornada_efecto', $jornada)->orderBy('id_usuario')->orderBy('jugada_en')->orderBy('id');
        }

        return $consulta->get()->map(fn (CartaUsuario $carta) => $this->rastro($carta))->values()->all();
    }

    /**
     * De qué depende una carta, deducido de su código de efecto (igual que hace
     * la explicación que ya ven los jugadores en Mis Cartas):
     * partido | mayoria | eventos | jornada | bonus | falta | otra
     */
    public function formaDe(string $codigoEfecto, ?string $categoria = null): string
    {
        $contiene = fn (string ...$trozos) => collect($trozos)->contains(fn ($t) => str_contains($codigoEfecto, $t));

        return match (true) {
            $categoria === 'Faltas' || str_starts_with($codigoEfecto, 'FAL-') => 'falta',
            $contiene('AMIGOARBITRO', 'MADRUGADOR', 'FILODESCANSO', 'TIEMPO') => 'eventos',
            $contiene('PALOMITAS', 'VISIONARIO') => 'mayoria',
            $contiene('CRACK') => 'jornada',
            $contiene('AMULETO') => 'bonus',
            $contiene('CHUTE', 'OJOHALCON', 'DOBLETE', 'PLENOGARANTIZADO') => 'partido',
            default => 'otra',
        };
    }

    // ------------------------------------------------------------------

    private function rastro(CartaUsuario $carta): array
    {
        $tipo = $carta->tipoCarta;
        $categoria = $tipo?->categoria?->nombre;
        $forma = $this->formaDe((string) $tipo?->codigo_efecto, $categoria);
        $nombre = fn ($usuario) => $usuario ? ($usuario->nombre_visible ?? $usuario->name) : 'Alguien';

        $pasos = [$this->pasoObtenida($carta)];

        if ($carta->revelada_en) {
            $pasos[] = $this->paso('La abrió', $carta->revelada_en);
        } elseif ($carta->estado === 'en_mano') {
            $pasos[] = $this->paso('Todavía no la ha abierto', null, 'Ya es suya, pero no la ha destapado: no la ve en su mano.');
        }

        if ($carta->jugada_en) {
            $pasos[] = $this->pasoJugada($carta, $forma, $nombre);
            $pasos[] = $this->pasoResultado($carta, $forma);
        } elseif ($carta->estado === 'descartada') {
            $pasos[] = $this->paso('La descartó', $carta->updated_at, 'La tiró de su mano sin jugarla.');
        } elseif ($carta->estado === 'retirada_manual') {
            $pasos[] = $this->paso('Retirada desde el admin', $carta->updated_at);
        } elseif ($carta->estado === 'retirada_por_catalogo') {
            $pasos[] = $this->paso('Retirada', $carta->updated_at, 'La carta dejó de existir en el catálogo.');
        } elseif ($carta->estado === 'en_mano' && $carta->revelada_en) {
            $pasos[] = $this->paso('En su mano, sin jugar', null);
        }

        return [
            'id' => $carta->id,
            'usuario' => ['id' => $carta->id_usuario, 'nombre' => $nombre($carta->usuario)],
            'tipo_carta' => $tipo,
            'nombre' => $tipo?->nombre ?? 'Carta desconocida',
            'categoria' => $categoria,
            'rareza' => $tipo?->rareza,
            'estado' => $carta->estado,
            'puntos' => (int) $carta->puntos_generados,
            'sobre' => $this->sobreQue($carta, $forma, $nombre),
            'id_partido' => $carta->id_partido,
            'jornada_efecto' => $carta->jornada_efecto,
            'pasos' => $pasos,
            'datos' => $carta->jugada_en ? $this->datos($carta, $forma) : [],
        ];
    }

    private function paso(string $titulo, $cuando, ?string $detalle = null): array
    {
        return ['titulo' => $titulo, 'cuando' => $cuando?->toIso8601String(), 'detalle' => $detalle];
    }

    private function pasoObtenida(CartaUsuario $carta): array
    {
        $titulo = match ($carta->origen) {
            'reparto_semanal' => "Le tocó en el reparto semanal de la jornada {$carta->jornada_obtenida}",
            'bonus_top3' => "La ganó por quedar en el Top 3 de la jornada {$carta->jornada_obtenida}",
            'manual' => 'Se la dio el admin a mano',
            default => 'La recibió ('.str_replace('_', ' ', (string) $carta->origen).')',
        };

        return $this->paso($titulo, $carta->obtenida_en);
    }

    private function etiquetaPartido(?CalendarioPartido $partido): string
    {
        if (! $partido) {
            return 'un partido que ya no existe';
        }

        return ($partido->equipoLocal->nombre_corto ?? $partido->equipoLocal->nombre ?? '?').' - '.($partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre ?? '?');
    }

    /** En una línea: sobre qué partido, rival o jornada se jugó. */
    private function sobreQue(CartaUsuario $carta, string $forma, callable $nombre): ?string
    {
        if (! $carta->jugada_en) {
            return null;
        }

        if ($forma === 'falta') {
            return $carta->id_usuario_objetivo === $carta->id_usuario
                ? "Para sí mismo · jornada {$carta->jornada_efecto}"
                : 'Contra '.$nombre($carta->usuarioObjetivo)." · jornada {$carta->jornada_efecto}";
        }

        return $carta->id_partido
            ? $this->etiquetaPartido($carta->partido)
            : "Toda la jornada {$carta->jornada_efecto}";
    }

    private function pasoJugada(CartaUsuario $carta, string $forma, callable $nombre): array
    {
        if ($forma === 'falta') {
            if ($carta->id_usuario_objetivo === $carta->id_usuario) {
                return $this->paso("La activó para protegerse en la jornada {$carta->jornada_efecto}", $carta->jugada_en);
            }

            $detalle = $carta->mensaje_falta ? 'Con el mensaje: «'.$carta->mensaje_falta.'»' : null;

            return $this->paso('La jugó contra '.$nombre($carta->usuarioObjetivo)." para la jornada {$carta->jornada_efecto}", $carta->jugada_en, $detalle);
        }

        if ($carta->id_partido) {
            return $this->paso('La jugó sobre '.$this->etiquetaPartido($carta->partido)." (jornada {$carta->jornada_efecto})", $carta->jugada_en);
        }

        return $this->paso("La jugó para toda la jornada {$carta->jornada_efecto}, sin elegir partido", $carta->jugada_en);
    }

    private function pasoResultado(CartaUsuario $carta, string $forma): array
    {
        $puntos = (int) $carta->puntos_generados;

        if ($carta->estado === 'jugada') {
            $cuando = match ($forma) {
                'eventos' => "Depende de los goles y tarjetas del partido: se resuelve al calcular los goleadores de la jornada {$carta->jornada_efecto}.",
                'falta' => "Su efecto se aplica en la jornada {$carta->jornada_efecto} y se da por resuelta al cerrarla.",
                default => "Se resuelve al cerrar la jornada {$carta->jornada_efecto}.",
            };

            $cierre = CierreJornada::where('id_liga', $carta->id_liga)->where('jornada', $carta->jornada_efecto)->where('cerrada', true)->first();
            if ($cierre && $forma === 'eventos' && ! $cierre->goleadores_calculados_en) {
                $cuando = 'La jornada ya está cerrada, pero falta calcular los goleadores: ahí es donde se resuelve esta carta.';
            } elseif ($cierre && ! in_array($forma, ['eventos', 'falta'], true)) {
                $cuando = 'La jornada ya está cerrada y la carta sigue esperando: no es lo normal, conviene mirarlo.';
            }

            return $this->paso('Esperando a resolverse', null, $cuando);
        }

        if ($carta->estado === 'resuelta_cumplida') {
            if ($puntos !== 0) {
                return $this->paso('Cumplida: '.($puntos > 0 ? '+' : '').$puntos.' punto(s)', null);
            }

            return $this->paso('Cumplida', null, $forma === 'bonus'
                ? 'No da puntos por sí misma: hizo que un fallo contara como acierto para el bonus de pleno.'
                : 'Hizo su efecto sin sumar puntos directos.');
        }

        if ($carta->estado === 'resuelta_no_cumplida') {
            return $this->paso('No tuvo efecto', null, $this->motivoSinEfecto($carta, $forma));
        }

        return $this->paso('Estado: '.str_replace('_', ' ', $carta->estado), null);
    }

    /**
     * Por qué no hizo nada. Mismas explicaciones que ve el jugador en Mis Cartas
     * (MisCartasController::motivoSinEfecto); si allí cambian, cambiar aquí.
     */
    private function motivoSinEfecto(CartaUsuario $carta, string $forma): ?string
    {
        $codigo = (string) $carta->tipoCarta?->codigo_efecto;

        if ($forma === 'falta') {
            return 'No llegó a aplicarse (por ejemplo, la paró un Escudo del rival).';
        }

        if ($carta->id_partido) {
            $pronostico = Pronostico::where('id_liga', $carta->id_liga)->where('id_usuario', $carta->id_usuario)->where('id_partido', $carta->id_partido)->exists();
            if (! $pronostico) {
                return 'No llegó a pronosticar ese partido, así que la carta no pudo aplicarse.';
            }
        }

        return match (true) {
            str_contains($codigo, 'OJOHALCON') => 'No acertó el resultado exacto de ese partido.',
            str_contains($codigo, 'DOBLETE') => 'Su pronóstico de ese partido fue un fallo: no había puntos que duplicar.',
            str_contains($codigo, 'PLENOGARANTIZADO') => 'Ya había acertado el resultado exacto por su cuenta, así que la carta no tuvo nada que mejorar.',
            str_contains($codigo, 'PALOMITAS') => 'Su pronóstico no coincidió con la mayoría de la liga en ese partido (o nadie más había pronosticado).',
            str_contains($codigo, 'CRACK') => 'No consiguió el resultado exacto en 2 partidos de la jornada.',
            str_contains($codigo, 'AMIGOARBITRO') => 'No hubo tarjeta roja en ese partido.',
            str_contains($codigo, 'MADRUGADOR') || str_contains($codigo, 'FILODESCANSO') || str_contains($codigo, 'TIEMPO') => 'No hubo ningún gol en la franja de minutos de esta carta.',
            str_contains($codigo, 'AMULETO') => 'No tuvo ningún fallo que proteger (o el partido elegido no fue un fallo).',
            default => null,
        };
    }

    /**
     * Los hechos que explican el resultado, tal como están guardados.
     *
     * @return array<int,array{etiqueta:string,valor:string}>
     */
    private function datos(CartaUsuario $carta, string $forma): array
    {
        $datos = [];
        $dato = function (string $etiqueta, string $valor) use (&$datos) {
            $datos[] = ['etiqueta' => $etiqueta, 'valor' => $valor];
        };

        if ($carta->id_partido && $carta->partido) {
            $partido = $carta->partido;

            $dato('Resultado del partido', $partido->estado === 'Jugado'
                ? "{$partido->goles_casa}-{$partido->goles_fuera}"
                : 'Sin jugar todavía ('.$partido->estado.')');

            $pronostico = Pronostico::where('id_liga', $carta->id_liga)->where('id_usuario', $carta->id_usuario)->where('id_partido', $carta->id_partido)->first();
            $dato('Su pronóstico', $pronostico
                ? "{$pronostico->goles_local_predicho}-{$pronostico->goles_visitante_predicho} ({$pronostico->resultado_1x2})"
                : 'No pronosticó este partido');

            $evento = EventoPuntos::where('id_liga', $carta->id_liga)->where('id_usuario', $carta->id_usuario)->where('id_partido', $carta->id_partido)
                ->whereIn('tipo_evento', array_keys(self::TIPOS_DE_PRONOSTICO))->first();
            if ($evento) {
                $texto = "{$evento->puntos} punto(s) por ".self::TIPOS_DE_PRONOSTICO[$evento->tipo_evento].', con las cartas ya aplicadas';
                $dato('Puntos de ese pronóstico', $evento->nota_carta ? $texto.' · '.$evento->nota_carta : $texto);
            }
        }

        if ($forma === 'mayoria' && $carta->id_partido) {
            $votos = Pronostico::where('id_liga', $carta->id_liga)->where('id_partido', $carta->id_partido)->where('id_usuario', '!=', $carta->id_usuario)
                ->selectRaw('resultado_1x2, COUNT(*) as total')->groupBy('resultado_1x2')->pluck('total', 'resultado_1x2');
            $dato('Lo que pronosticó el resto', $votos->isEmpty()
                ? 'Nadie más pronosticó este partido'
                : $votos->sortDesc()->map(fn ($total, $signo) => "{$signo}: {$total}")->implode(' · '));
        }

        if ($forma === 'eventos' && $carta->id_partido) {
            $eventos = EventoPartido::where('id_partido', $carta->id_partido)->orderBy('minuto')->get()
                ->filter(fn ($e) => str_contains((string) $e->tipo_evento, 'gol') || str_contains((string) $e->tipo_evento, 'roja'));
            $total = EventoPartido::where('id_partido', $carta->id_partido)->count();
            $dato('Goles y rojas del partido', $total === 0
                ? 'Aún no hay eventos importados de este partido'
                : ($eventos->isEmpty()
                    ? 'Ninguno'
                    : $eventos->map(fn ($e) => str_replace('_', ' ', $e->tipo_evento)." {$e->minuto}'")->implode(' · ')));
        }

        if (in_array($forma, ['jornada', 'bonus'], true)) {
            $eventosJornada = EventoPuntos::where('id_liga', $carta->id_liga)->where('id_usuario', $carta->id_usuario)->where('jornada', $carta->jornada_efecto)->get();

            if ($eventosJornada->isEmpty()) {
                $dato("Sus puntos de la jornada {$carta->jornada_efecto}", 'Todavía no hay: la jornada no está cerrada');
            } else {
                $exactos = $eventosJornada->where('tipo_evento', 'AciertoExacto')->count();
                $aciertos = $eventosJornada->whereIn('tipo_evento', ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'])->count();
                $fallos = $eventosJornada->where('tipo_evento', 'Fallo')->count();
                $dato("Sus pronósticos de la jornada {$carta->jornada_efecto}", "{$aciertos} acierto(s) de signo, de ellos {$exactos} exacto(s), y {$fallos} fallo(s)");

                if ($forma === 'bonus') {
                    $pleno = $eventosJornada->firstWhere('tipo_evento', 'BonusPleno');
                    $dato('Bonus de pleno', $pleno ? "+{$pleno->puntos} punto(s)" : 'No lo consiguió');
                }
            }
        }

        return $datos;
    }
}
