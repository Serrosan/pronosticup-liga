<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * El resumen de puntos de UNA jornada para cada jugador: lo que va en el correo
 * y en el aviso de "jornada cerrada".
 *
 * Las cuentas son las mismas que el detalle de puntos de la clasificación
 * (ClasificacionController::detalle): pronósticos, bonus de pleno, goleadores
 * y cartas suman SIEMPRE el total de la jornada, sin contar nada dos veces.
 */
class ResumenPuntosJornadaService
{
    private const TIPOS_RESULTADO = ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2', 'Fallo'];

    private const TIPOS_ACIERTO = ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'];

    /** Lo que es igual para todos los jugadores de la liga: se calcula una sola vez. */
    public function contexto(Liga $liga, int $jornada): array
    {
        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        return [
            'liga' => $liga,
            'jornada' => $jornada,
            'ids_partidos' => $idsPartidos,
            'goles' => EventoPartido::whereIn('id_partido', $idsPartidos)
                ->where('tipo_evento', 'gol')
                ->get(['id_jugador'])
                ->countBy('id_jugador'),
            'posiciones' => $this->posiciones($liga->id),
            // "Antes de esta jornada" = todo menos esta jornada. Así la flecha también
            // tiene sentido con un aplazado que se cierra después de otras jornadas.
            'anteriores' => $this->posiciones($liga->id, $jornada),
        ];
    }

    /** A quién avisar: miembros de la liga que pronosticaron o eligieron goleadores esa jornada. */
    public function usuariosConActividad(array $contexto): Collection
    {
        $liga = $contexto['liga'];

        $ids = Pronostico::where('id_liga', $liga->id)
            ->whereIn('id_partido', $contexto['ids_partidos'])
            ->pluck('id_usuario')
            ->merge(GoleadorJornada::where('id_liga', $liga->id)->where('jornada', $contexto['jornada'])->pluck('id_usuario'))
            ->unique()
            ->values();

        return $liga->usuarios()->whereIn('users.id', $ids)->get();
    }

    public function paraUsuario(array $contexto, User $usuario): array
    {
        $liga = $contexto['liga'];
        $jornada = $contexto['jornada'];

        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->where('jornada', $jornada)
            ->get();

        $resultados = $eventos->whereIn('tipo_evento', self::TIPOS_RESULTADO);

        // Igual que el detalle de la clasificación: las cartas ligadas a un partido ya
        // vienen sumadas dentro de los puntos de ese partido, así que se restan de
        // "pronósticos" y se cuentan en "cartas".
        $cartasDePartido = (int) CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->where('jornada_efecto', $jornada)
            ->where('estado', 'resuelta_cumplida')
            ->whereNotNull('id_partido')
            ->sum('puntos_generados');

        $total = (int) $eventos->sum('puntos');
        $pronosticos = (int) $resultados->sum('puntos')
            + (int) $eventos->where('tipo_evento', 'CartaEventoPartido')->sum('puntos')
            - $cartasDePartido;
        $bonusPleno = (int) $eventos->where('tipo_evento', 'BonusPleno')->sum('puntos');
        $puntosGoleadores = (int) $eventos->where('tipo_evento', 'GolesGoleadorElegido')->sum('puntos');
        // Lo que queda son las cartas: así las cuatro líneas suman exactamente el total.
        $cartas = $total - $pronosticos - $bonusPleno - $puntosGoleadores;

        $selecciones = GoleadorJornada::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->where('jornada', $jornada)
            ->with('jugador')
            ->get();

        $goleadores = $selecciones
            ->map(fn ($s) => [
                'nombre' => $this->nombreJugador($s->jugador),
                'goles' => (int) $contexto['goles']->get($s->id_jugador, 0),
            ])
            ->filter(fn ($g) => $g['goles'] > 0)
            ->sortByDesc('goles')
            ->values()
            ->all();

        $conCartas = $liga->tipo === 'ConExtras' && CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', ['resuelta_cumplida', 'resuelta_no_cumplida'])
            ->exists();

        $posicion = $contexto['posiciones'][$usuario->id] ?? null;
        $anterior = $contexto['anteriores'][$usuario->id] ?? null;

        return [
            'jornada' => $jornada,
            'total' => $total,
            'pronosticos' => $pronosticos,
            'partidos' => $resultados->count(),
            'aciertos' => $resultados->whereIn('tipo_evento', self::TIPOS_ACIERTO)->count(),
            'exactos' => $resultados->where('tipo_evento', 'AciertoExacto')->count(),
            'bonus_pleno' => $bonusPleno,
            'goleadores_puntos' => $puntosGoleadores,
            'goleadores_elegidos' => $selecciones->count(),
            'goleadores' => $goleadores,
            'mostrar_cartas' => $conCartas || $cartas !== 0,
            'cartas' => $cartas,
            'posicion' => $posicion,
            // Positivo = sube puestos; null si antes no tenía posición.
            'cambio_posicion' => ($posicion !== null && $anterior !== null) ? $anterior - $posicion : null,
        ];
    }

    /**
     * Posición de cada jugador (1 = primero) según la suma de sus puntos, con el
     * mismo orden que la clasificación. $sinJornada deja fuera esa jornada.
     *
     * @return array<int,int>
     */
    private function posiciones(int $idLiga, ?int $sinJornada = null): array
    {
        return EventoPuntos::where('id_liga', $idLiga)
            ->when($sinJornada !== null, fn ($q) => $q->where('jornada', '!=', $sinJornada))
            ->orderBy('id')
            ->get(['id_usuario', 'puntos'])
            ->groupBy('id_usuario')
            ->map(fn ($grupo) => (int) $grupo->sum('puntos'))
            ->sortDesc()
            ->keys()
            ->values()
            ->mapWithKeys(fn ($idUsuario, $indice) => [(int) $idUsuario => $indice + 1])
            ->all();
    }

    private function nombreJugador($jugador): string
    {
        if (! $jugador) {
            return '?';
        }

        return $jugador->nombre_camiseta ?: trim(($jugador->nombre ?? '').' '.($jugador->apellidos ?? ''));
    }
}
