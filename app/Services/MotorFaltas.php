<?php

namespace App\Services;

use App\Models\CartaUsuario;
use App\Models\EventoPartido;
use App\Models\Pronostico;
use Illuminate\Support\Collection;

class MotorFaltas
{
    private const CODIGOS_ESCUDO = ['FAL-PCOM-ESCUDO'];
    private const CODIGO_FALLO_CLAMOROSO = 'FAL-PCOM-FALLO';

    /**
     * Registro de Faltas tipo "requisito de pronóstico": exigen que la víctima
     * incluya al menos X pronósticos de un tipo concreto (Local/Visitante)
     * entre TODOS los de la jornada afectada.
     */
    private const REQUISITOS_PRONOSTICO = [
        'FAL-COM-CASA' => ['tipo' => 'Local', 'cantidad' => 2],
        'FAL-PCOM-CASA' => ['tipo' => 'Local', 'cantidad' => 3],
        'FAL-LEG-CASA' => ['tipo' => 'Local', 'cantidad' => 5],
        'FAL-COM-VISITA' => ['tipo' => 'Visitante', 'cantidad' => 2],
        'FAL-PCOM-VISITA' => ['tipo' => 'Visitante', 'cantidad' => 3],
        'FAL-RAR-VISITA' => ['tipo' => 'Visitante', 'cantidad' => 4],
        'FAL-LEG-VISITA' => ['tipo' => 'Visitante', 'cantidad' => 5],
        // Resultado Gafas: faltaban aquí y por eso no se aplicaban (jornada 9).
        'FAL-COM-EMPATE' => ['tipo' => 'Empate', 'cantidad' => 1],
        'FAL-PCOM-EMPATE' => ['tipo' => 'Empate', 'cantidad' => 2],
    ];

    /**
     * Registro de Expulsión: cada código bloquea 1 o varias categorías de
     * carta. Añadir una Expulsión nueva (ej. una que bloquee solo Jugadas)
     * es 1 línea aquí, sin tocar nada más.
     */
    private const CATEGORIAS_BLOQUEADAS_POR_EXPULSION = [
        'FAL-LEG-EXPULSION' => ['Jugadas', 'Faltas'],
        'FAL-RAR-EXPULSION' => ['Faltas'],
    ];

    /**
     * Sin Comodines: no bloquea una categoría entera, bloquea cartas
     * concretas por su codigo_efecto — pensado para las de "red de
     * seguridad" (Amuleto, Pleno Garantizado), no para toda una categoría.
     */
    private const CODIGOS_BLOQUEADOS_POR_SIN_COMODINES = [
        'JUG-PCOM-AMULETO', 'JUG-RAR-AMULETO', 'JUG-LEG-AMULETO',
        'JUG-RAR-PLENOGARANTIZADO',
    ];

    /**
     * ¿El objetivo tiene un Escudo activo protegiéndolo justo en la jornada
     * en la que caería esta Falta? Si lo hay, lo consume (queda resuelto) y
     * lo devuelve — quien llama decide qué hacer con ese dato.
     */
    public function consumirEscudoSiActivo(int $idLiga, int $idUsuarioObjetivo, int $jornadaEfecto): ?CartaUsuario
    {
        $escudo = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuarioObjetivo)
            ->where('jornada_efecto', $jornadaEfecto)
            ->where('estado', 'jugada')
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', self::CODIGOS_ESCUDO))
            ->first();

        if ($escudo) {
            $escudo->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => 0]);
        }

        return $escudo;
    }

    /**
     * Reglas anti-abuso confirmadas: no repetir el mismo rival 2 semanas
     * seguidas, y máximo de Faltas que una persona puede recibir en la misma
     * jornada = mitad de miembros de la liga, redondeado hacia abajo.
     * Devuelve null si no hay ningún problema, o el mensaje de error si lo hay.
     */
    public function comprobarAntiAbuso(int $idLiga, int $idAtacante, int $idObjetivo, int $jornadaEfecto, int $totalMiembrosLiga): ?string
    {
        $rivalRepetido = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idAtacante)
            ->where('id_usuario_objetivo', $idObjetivo)
            ->where('jornada_efecto', $jornadaEfecto - 1)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->exists();

        if ($rivalRepetido) {
            return 'No puedes jugar una Falta contra el mismo rival 2 semanas seguidas.';
        }

        $maximoRecibidas = intdiv($totalMiembrosLiga, 2);

        $yaRecibidas = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idObjetivo)
            ->where('jornada_efecto', $jornadaEfecto)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->count();

        if ($maximoRecibidas > 0 && $yaRecibidas >= $maximoRecibidas) {
            return "Ese jugador ya ha alcanzado el máximo de Faltas recibidas esta jornada ({$maximoRecibidas}).";
        }

        return null;
    }

    /**
     * Igual que comprobarAntiAbuso(), pero para TODOS los posibles rivales a la vez —
     * pensado para que el selector muestre el motivo antes de elegir, no después de
     * confirmar. Solo devuelve entradas para los rivales que sí quedan bloqueados.
     *
     * @return array<int, string> id_usuario => motivo
     */
    public function motivosBloqueoPorRival(int $idLiga, int $idAtacante, int $jornadaEfecto, int $totalMiembrosLiga): array
    {
        $motivos = [];

        CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idAtacante)
            ->where('jornada_efecto', $jornadaEfecto - 1)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->whereNotNull('id_usuario_objetivo')
            ->pluck('id_usuario_objetivo')
            ->unique()
            ->each(function ($idRival) use (&$motivos) {
                $motivos[$idRival] = 'Ya le jugaste una Falta la semana pasada.';
            });

        $maximoRecibidas = intdiv($totalMiembrosLiga, 2);

        if ($maximoRecibidas > 0) {
            CartaUsuario::where('id_liga', $idLiga)
                ->where('jornada_efecto', $jornadaEfecto)
                ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
                ->whereNotNull('id_usuario_objetivo')
                ->selectRaw('id_usuario_objetivo, COUNT(*) as total')
                ->groupBy('id_usuario_objetivo')
                ->havingRaw('COUNT(*) >= ?', [$maximoRecibidas])
                ->pluck('id_usuario_objetivo')
                ->each(function ($idRival) use (&$motivos, $maximoRecibidas) {
                    $motivos[$idRival] ??= "Ya ha alcanzado el máximo de Faltas recibidas esta jornada ({$maximoRecibidas}).";
                });
        }

        return $motivos;
    }

    /**
     * ¿Este usuario tiene una Expulsión activa que le impide jugar cartas de
     * esta categoría, justo en esta jornada? Se comprueba con el número de
     * jornada que está a punto de abrirse (donde caería el efecto).
     */
    public function estaExpulsadoDe(int $idLiga, int $idUsuario, int $jornada, string $categoria): bool
    {
        $expulsiones = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida'])
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', array_keys(self::CATEGORIAS_BLOQUEADAS_POR_EXPULSION)))
            ->with('tipoCarta')
            ->get();

        foreach ($expulsiones as $expulsion) {
            $categoriasBloqueadas = self::CATEGORIAS_BLOQUEADAS_POR_EXPULSION[$expulsion->tipoCarta->codigo_efecto];

            if (in_array($categoria, $categoriasBloqueadas, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Esta carta concreta está bloqueada por un "Sin Comodines" activo
     * contra este usuario, justo en esta jornada?
     */
    public function estaBloqueadaPorSinComodines(int $idLiga, int $idUsuario, int $jornada, string $codigoEfecto): bool
    {
        if (! in_array($codigoEfecto, self::CODIGOS_BLOQUEADOS_POR_SIN_COMODINES, true)) {
            return false;
        }

        return CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida'])
            ->whereHas('tipoCarta', fn ($q) => $q->where('codigo_efecto', 'FAL-PCOM-SINCOMODINES'))
            ->exists();
    }

    /**
     * Prevalidación real: comprueba si guardar un pronóstico dejaría
     * MATEMÁTICAMENTE IMPOSIBLE cumplir una Falta de tipo "requisito" activa
     * contra este usuario — no obliga a que cada pronóstico individual sea
     * del tipo exigido, solo bloquea el momento en que ya no queden
     * suficientes partidos por pronosticar para poder alcanzarlo.
     *
     * @param int $totalPronosticosConEsteIncluido Cuántos pronósticos tiene ya
     *   guardados esta jornada, CONTANDO el que se está a punto de guardar ahora.
     * @param Collection $tiposConEsteIncluido Los resultado_1x2 (Local/Empate/
     *   Visitante) de esos mismos pronósticos, con el nuevo ya incluido.
     */
    public function comprobarRequisitoPronostico(int $idLiga, int $idUsuario, int $jornada, int $totalPartidosJornada, int $totalPronosticosConEsteIncluido, Collection $tiposConEsteIncluido): ?string
    {
        $requisitos = $this->requisitosActivos($idLiga, $idUsuario, $jornada);

        if ($requisitos->isEmpty()) {
            return null;
        }

        // Los tipos no se solapan (un pronóstico es Local, Empate o Visitante),
        // así que con varias Faltas a la vez lo que falte de cada una se suma y
        // tiene que caber en los partidos que quedan por pronosticar.
        $partidosAunSinPredecir = $totalPartidosJornada - $totalPronosticosConEsteIncluido;

        if ($this->deficit($requisitos, $tiposConEsteIncluido) > $partidosAunSinPredecir) {
            $lista = $requisitos->map(fn ($r) => "{$r['cantidad']} pronóstico(s) de \"{$r['tipo']}\"")->implode(' y ');
            $quien = $requisitos->count() > 1 ? 'Faltas activas' : 'una Falta activa';

            return "Tienes {$quien} contra ti: debes incluir al menos {$lista} esta jornada, y guardar así ya lo haría imposible de cumplir.";
        }

        return null;
    }

    /**
     * Versión de solo lectura de comprobarRequisitoPronostico() — para avisar mientras
     * el usuario va pronosticando (con progreso real: cuántos llevas, cuántos te
     * quedan), en vez de solo rechazarle al guardar el partido que lo rompe.
     * Devuelve null si no hay ninguna Falta de este tipo activa contra él.
     */
    public function estadoRequisitoPronostico(int $idLiga, int $idUsuario, int $jornada, Collection $idsPartidosJornada): ?array
    {
        $requisitos = $this->requisitosActivos($idLiga, $idUsuario, $jornada);

        if ($requisitos->isEmpty()) {
            return null;
        }

        $misPronosticos = Pronostico::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuario)
            ->whereIn('id_partido', $idsPartidosJornada)
            ->pluck('resultado_1x2');

        $partidosSinPronosticar = $idsPartidosJornada->count() - $misPronosticos->count();
        $estados = $requisitos->map(fn ($r) => [
            'tipo' => $r['tipo'],
            'cantidad' => $r['cantidad'],
            'cumplidos' => $misPronosticos->filter(fn ($tipo) => $tipo === $r['tipo'])->count(),
        ]);

        // Se enseña primero la que aún no está cumplida; con varias, todas van en 'requisitos'.
        $principal = $estados->first(fn ($e) => $e['cumplidos'] < $e['cantidad']) ?? $estados->first();

        return $principal + [
            'partidos_sin_pronosticar' => $partidosSinPronosticar,
            // Sigue siendo posible cumplirlo (todas a la vez) con lo que queda por pronosticar.
            'cumplible' => $this->deficit($requisitos, $misPronosticos) <= $partidosSinPronosticar,
            'requisitos' => $estados->values()->all(),
        ];
    }

    /**
     * Las Faltas de "requisito de pronóstico" activas contra este usuario en esa
     * jornada, una por tipo (si hay dos del mismo tipo, manda la más exigente).
     *
     * @return Collection<int,array{tipo:string,cantidad:int}>
     */
    private function requisitosActivos(int $idLiga, int $idUsuario, int $jornada): Collection
    {
        return CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->where('estado', 'jugada')
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', array_keys(self::REQUISITOS_PRONOSTICO)))
            ->with('tipoCarta')
            ->get()
            ->map(fn ($carta) => self::REQUISITOS_PRONOSTICO[$carta->tipoCarta->codigo_efecto])
            ->groupBy('tipo')
            ->map(fn ($mismoTipo) => $mismoTipo->sortByDesc('cantidad')->first())
            ->values();
    }

    /** Cuántos pronósticos faltan, sumando todos los requisitos, para cumplirlos. */
    private function deficit(Collection $requisitos, Collection $tipos): int
    {
        return (int) $requisitos->sum(fn ($r) => max(0, $r['cantidad'] - $tipos->filter(fn ($tipo) => $tipo === $r['tipo'])->count()));
    }

    public function tieneFalloClamorosoActivo(int $idLiga, int $idUsuario, int $jornada): bool
    {
        return CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->where('estado', 'jugada')
            ->whereHas('tipoCarta', fn ($q) => $q->where('codigo_efecto', self::CODIGO_FALLO_CLAMOROSO))
            ->exists();
    }

    /**
     * Los N jugadores con más goles reales marcados en toda la temporada —
     * misma consulta que ya usa EstadisticasJugadoresController::goleadores(),
     * reutilizada aquí en vez de duplicarla (un controller nunca se llama
     * desde otro como si fuera un servicio).
     */
    public function topGoleadoresRealesIds(int $cantidad = 3): array
    {
        return EventoPartido::where('tipo_evento', 'gol')
            ->whereNotNull('id_jugador')
            ->selectRaw('id_jugador, count(*) as goles')
            ->groupBy('id_jugador')
            ->orderByDesc('goles')
            ->limit($cantidad)
            ->pluck('id_jugador')
            ->all();
    }

    /**
     * Al cerrar/recalcular una jornada, las Faltas cuyo efecto ya ha pasado
     * salen de "jugada" (así ni bloquean jugar otra Falta ni se acumulan en
     * "Amenazas"). Un Escudo que nadie llegó a gastar pasa a "no cumplida".
     */
    public function resolverFaltasDeLaJornada(int $idLiga, int $jornada): void
    {
        CartaUsuario::where('id_liga', $idLiga)
            ->where('estado', 'jugada')
            ->where('jornada_efecto', '<=', $jornada)
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', 'Faltas'))
            ->with('tipoCarta')
            ->get()
            ->each(function ($carta) {
                $escudoSinGastar = in_array($carta->tipoCarta->codigo_efecto, self::CODIGOS_ESCUDO, true);

                $carta->update([
                    'estado' => $escudoSinGastar ? 'resuelta_no_cumplida' : 'resuelta_cumplida',
                    'puntos_generados' => 0,
                ]);
            });
    }
}