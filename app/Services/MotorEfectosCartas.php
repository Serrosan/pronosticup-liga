<?php

namespace App\Services;

use App\Models\CartaUsuario;
use App\Models\ConfiguracionPuntos;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\Pronostico;
use App\Services\EfectosCartas\BonusFijoPartido;
use App\Services\EfectosCartas\BonusSiCoincideMayoria;
use App\Services\EfectosCartas\BonusSiDosExactos;
use App\Services\EfectosCartas\BonusSiExacto;
use App\Services\EfectosCartas\BonusSiGolEnFranja;
use App\Services\EfectosCartas\BonusSiTarjetaRoja;
use App\Services\EfectosCartas\Doblete;
use App\Services\EfectosCartas\PlenoGarantizado;
use App\Services\EfectosCartas\ProtegeAutomatico;
use App\Services\EfectosCartas\ProtegeElegido;
use Illuminate\Support\Collection;

class MotorEfectosCartas
{
    /**
     * Estados en los que una carta jugada sigue siendo "recalculable". Todo el
     * motor busca cartas en cualquiera de los 3, y RE-DERIVA su estado en cada
     * pasada — así cerrar, recalcular puntos o recalcular eventos se puede
     * repetir las veces que haga falta sin perder bonus ni duplicarlos.
     */
    private const ESTADOS_RESOLVIBLES = ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'];

    // ------------------------------------------------------------------
    // FORMA 1 — ajusta los puntos de UN partido elegido de antemano.
    // FORMA 2 — compara tu signo contra la mayoría del resto de la liga.
    // Ambas se resuelven al calcular los puntos de cada pronóstico, por eso
    // comparten método de entrada. Si hay varias cartas sobre el mismo
    // partido se aplican en el orden en que se jugaron.
    // ------------------------------------------------------------------

    private function ajustadoresPartido(): array
    {
        return [
            'JUG-COM-CHUTE' => new BonusFijoPartido(1),
            'JUG-PCOM-CHUTE' => new BonusFijoPartido(2),
            'JUG-RAR-CHUTE' => new BonusFijoPartido(3),
            'JUG-LEG-CHUTE' => new BonusFijoPartido(5),
            'JUG-COM-OJOHALCON' => new BonusSiExacto(2),
            'JUG-PCOM-OJOHALCON' => new BonusSiExacto(4),
            'JUG-PCOM-DOBLETE' => new Doblete(),
            'JUG-RAR-PLENOGARANTIZADO' => new PlenoGarantizado(),
        ];
    }

    private function comparadoresMayoria(): array
    {
        return [
            'JUG-COM-PALOMITAS' => new BonusSiCoincideMayoria(1),
            'JUG-PCOM-PALOMITAS' => new BonusSiCoincideMayoria(2),
            'JUG-RAR-PALOMITAS' => new BonusSiCoincideMayoria(3),
            'JUG-LEG-PALOMITAS' => new BonusSiCoincideMayoria(5),
            // Visionario, cuando exista: new BonusSiNoCoincideMayoria(1)
        ];
    }

    /**
     * Si 2+ signos empatan como más votados se desempata "al azar", pero de
     * forma ESTABLE (según partido y usuario) para que recalcular la jornada
     * nunca cambie el resultado. Nunca cuenta el voto del propio usuario.
     */
    private function calcularMayoriaSigno(int $idLiga, int $idPartido, int $idUsuarioExcluir): ?string
    {
        $conteos = Pronostico::where('id_liga', $idLiga)
            ->where('id_partido', $idPartido)
            ->where('id_usuario', '!=', $idUsuarioExcluir)
            ->selectRaw('resultado_1x2, COUNT(*) as total')
            ->groupBy('resultado_1x2')
            ->pluck('total', 'resultado_1x2');

        if ($conteos->isEmpty()) {
            return null;
        }

        $maximo = $conteos->max();
        $empatados = $conteos->filter(fn ($total) => $total === $maximo)->keys()->sort()->values();

        return $empatados[($idPartido + $idUsuarioExcluir) % $empatados->count()];
    }

    /**
     * @return array{puntos: int, nota: ?string}
     */
    public function ajustarPuntosPartido(int $idLiga, int $idUsuario, int $idPartido, int $puntosBase, string $tipoEventoReal, string $miSigno, ConfiguracionPuntos $config): array
    {
        $cartas = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuario)
            ->where('id_partido', $idPartido)
            ->whereIn('estado', self::ESTADOS_RESOLVIBLES)
            ->with('tipoCarta')
            ->orderBy('jugada_en')
            ->orderBy('id')
            ->get();

        $ajustadores = $this->ajustadoresPartido();
        $comparadores = $this->comparadoresMayoria();

        $puntos = $puntosBase;
        $nota = null;

        foreach ($cartas as $carta) {
            $codigo = $carta->tipoCarta->codigo_efecto;
            $antes = $puntos;

            if (isset($ajustadores[$codigo])) {
                $resultado = $ajustadores[$codigo]->calcular($puntos, $tipoEventoReal, $config);
                $puntos = $resultado['puntos'];
                $nota = $resultado['nota'] ?? $nota;
            } elseif (isset($comparadores[$codigo])) {
                $mayoria = $this->calcularMayoriaSigno($idLiga, $idPartido, $idUsuario);
                $puntos += $comparadores[$codigo]->evaluar($miSigno, $mayoria)['puntos'];
            } else {
                continue; // carta de otra forma (Amuleto, eventos...) sobre este partido
            }

            $delta = $puntos - $antes;

            $carta->update([
                'estado' => $delta > 0 ? 'resuelta_cumplida' : 'resuelta_no_cumplida',
                'puntos_generados' => $delta,
            ]);
        }

        return ['puntos' => $puntos, 'nota' => $nota];
    }

    // ------------------------------------------------------------------
    // FORMA 4 — modifica qué cuenta como "acierto" para el bonus de pleno.
    // Se resuelve una vez por usuario, tras conocer TODOS los fallos de la
    // jornada. Solo mira cartas cuyo efecto cae en ESTA jornada.
    // ------------------------------------------------------------------

    private function protectoresBonus(): array
    {
        return [
            'JUG-PCOM-AMULETO' => new ProtegeElegido(),
            'JUG-RAR-AMULETO' => new ProtegeAutomatico(1),
            'JUG-LEG-AMULETO' => new ProtegeAutomatico(2),
        ];
    }

    public function ajustarAciertosParaBonus(int $idLiga, int $idUsuario, int $jornada, int $aciertosReales, Collection $partidosFallidos): int
    {
        $registro = $this->protectoresBonus();

        $cartas = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', self::ESTADOS_RESOLVIBLES)
            ->with('tipoCarta')
            ->orderBy('id')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $protegidos = collect();

        foreach ($cartas as $carta) {
            $disponibles = $partidosFallidos->diff($protegidos);
            $protegidosPorEstaCarta = $registro[$carta->tipoCarta->codigo_efecto]->partidosAProteger($carta, $disponibles);

            if ($protegidosPorEstaCarta->isNotEmpty()) {
                $protegidos = $protegidos->merge($protegidosPorEstaCarta);
                $carta->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => 0]);
            } else {
                $carta->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]);
            }
        }

        return $aciertosReales + $protegidos->count();
    }

    // ------------------------------------------------------------------
    // FORMA 3 — bono sobre varios partidos de la jornada, SIN elegir ninguno.
    // ------------------------------------------------------------------

    private function bonosMultiPartido(): array
    {
        return [
            'JUG-LEG-CRACK' => new BonusSiDosExactos(4),
        ];
    }

    /**
     * Usado por MisCartasController al jugar una carta: ¿pide elegir un partido?
     * No lo piden las de Forma 3 (bono de toda la jornada) ni los Amuletos
     * "automáticos" (Rara y Legendaria: protegen los partidos que falles).
     */
    public function requiereEleccionDePartido(string $codigoEfecto): bool
    {
        if (array_key_exists($codigoEfecto, $this->bonosMultiPartido())) {
            return false;
        }

        return ! (($this->protectoresBonus()[$codigoEfecto] ?? null) instanceof ProtegeAutomatico);
    }

    /**
     * @return array<int, int> puntos extra por id_usuario
     */
    public function resolverBonosMultiPartido(int $idLiga, int $jornada): array
    {
        EventoPuntos::where('id_liga', $idLiga)
            ->where('jornada', $jornada)
            ->where('tipo_evento', 'CartaBonoJornada')
            ->delete();

        $registro = $this->bonosMultiPartido();

        $cartas = CartaUsuario::where('id_liga', $idLiga)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', self::ESTADOS_RESOLVIBLES)
            ->with('tipoCarta')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $puntosPorUsuario = [];

        foreach ($cartas as $carta) {
            $eventosDeLaJornada = EventoPuntos::where('id_liga', $idLiga)
                ->where('id_usuario', $carta->id_usuario)
                ->where('jornada', $jornada)
                ->whereIn('tipo_evento', ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2', 'Fallo'])
                ->get();

            $resultado = $registro[$carta->tipoCarta->codigo_efecto]->evaluar($eventosDeLaJornada);

            if ($resultado['cumplido']) {
                EventoPuntos::create([
                    'id_usuario' => $carta->id_usuario,
                    'id_liga' => $idLiga,
                    'id_partido' => null,
                    'jornada' => $jornada,
                    'tipo_evento' => 'CartaBonoJornada',
                    'puntos' => $resultado['puntos'],
                ]);

                $carta->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => $resultado['puntos']]);
                $puntosPorUsuario[$carta->id_usuario] = ($puntosPorUsuario[$carta->id_usuario] ?? 0) + $resultado['puntos'];
            } else {
                $carta->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]);
            }
        }

        return $puntosPorUsuario;
    }

    // ------------------------------------------------------------------
    // FORMA 5 — depende de eventos del partido (tarjetas/goles). Se resuelve
    // al recalcular eventos, NUNCA al cerrar jornada. Recalcular es seguro:
    // borra y recrea sus propios puntos, y re-deriva el estado de la carta.
    // ------------------------------------------------------------------

    private function efectosEventosPartido(): array
    {
        return [
            'JUG-COM-AMIGOARBITRO' => new BonusSiTarjetaRoja(2),
            'JUG-COM-MADRUGADOR' => new BonusSiGolEnFranja(0, 30, 1),
            'JUG-PCOM-FILODESCANSO' => new BonusSiGolEnFranja(30, 60, 2),
            'JUG-RAR-TIEMPO' => new BonusSiGolEnFranja(60, 90, 3),
        ];
    }

    public function resolverEfectosDeEventos(int $idLiga, int $jornada, Collection $idsPartidos): int
    {
        EventoPuntos::where('id_liga', $idLiga)
            ->where('jornada', $jornada)
            ->where('tipo_evento', 'CartaEventoPartido')
            ->delete();

        $registro = $this->efectosEventosPartido();

        $cartas = CartaUsuario::whereIn('id_partido', $idsPartidos)
            ->where('id_liga', $idLiga)
            ->whereIn('estado', self::ESTADOS_RESOLVIBLES)
            ->with('tipoCarta')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $creados = 0;

        foreach ($cartas as $carta) {
            $eventosDelPartido = EventoPartido::where('id_partido', $carta->id_partido)->get();
            $resultado = $registro[$carta->tipoCarta->codigo_efecto]->evaluar($eventosDelPartido);

            if ($resultado['cumplido']) {
                EventoPuntos::create([
                    'id_usuario' => $carta->id_usuario,
                    'id_liga' => $idLiga,
                    'id_partido' => $carta->id_partido,
                    'jornada' => $jornada,
                    'tipo_evento' => 'CartaEventoPartido',
                    'puntos' => $resultado['puntos'],
                ]);

                $carta->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => $resultado['puntos']]);
                $creados++;
            } else {
                $carta->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]);
            }
        }

        return $creados;
    }

    // ------------------------------------------------------------------
    // FORMA 6 — bono si aciertas el 1X2 de 2 partidos ELEGIDOS de antemano.
    // Registro vacío hasta que exista Doble Filo en el catálogo.
    // ------------------------------------------------------------------

    private function bonosDoblePartidoElegido(): array
    {
        return [
            // 'JUG-PCOM-DOBLEFILO' => 3,
        ];
    }

    public function requiereDosPartidos(string $codigoEfecto): bool
    {
        return array_key_exists($codigoEfecto, $this->bonosDoblePartidoElegido());
    }

    /**
     * @return array<int, int> puntos extra por id_usuario
     */
    public function resolverBonoDoblePartidoElegido(int $idLiga, int $jornada): array
    {
        EventoPuntos::where('id_liga', $idLiga)
            ->where('jornada', $jornada)
            ->where('tipo_evento', 'CartaDoblePartido')
            ->delete();

        $registro = $this->bonosDoblePartidoElegido();

        if (empty($registro)) {
            return [];
        }

        $cartas = CartaUsuario::where('id_liga', $idLiga)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', self::ESTADOS_RESOLVIBLES)
            ->with(['tipoCarta', 'cartaPartidos'])
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $puntosPorUsuario = [];

        foreach ($cartas as $carta) {
            $partidosElegidos = $carta->cartaPartidos;

            if ($partidosElegidos->count() !== 2) {
                $carta->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]);
                continue;
            }

            $ambosAcertados = $partidosElegidos->every(function ($cp) use ($idLiga, $carta) {
                return EventoPuntos::where('id_liga', $idLiga)
                    ->where('id_usuario', $carta->id_usuario)
                    ->where('id_partido', $cp->id_partido)
                    ->whereIn('tipo_evento', ['Acierto1x2', 'AciertoDiferencia', 'AciertoExacto'])
                    ->exists();
            });

            $bonus = $registro[$carta->tipoCarta->codigo_efecto];

            if ($ambosAcertados) {
                EventoPuntos::create([
                    'id_usuario' => $carta->id_usuario,
                    'id_liga' => $idLiga,
                    'id_partido' => null,
                    'jornada' => $jornada,
                    'tipo_evento' => 'CartaDoblePartido',
                    'puntos' => $bonus,
                ]);

                $carta->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => $bonus]);
                $puntosPorUsuario[$carta->id_usuario] = ($puntosPorUsuario[$carta->id_usuario] ?? 0) + $bonus;
            } else {
                $carta->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]);
            }
        }

        return $puntosPorUsuario;
    }

    // ------------------------------------------------------------------
    // BARRIDO FINAL — al cerrar/recalcular una jornada, las Jugadas que
    // ningún efecto llegó a tocar (p. ej. Chute Extra sobre un partido que
    // el usuario no pronosticó) pasan a "no cumplida" en vez de quedarse
    // eternamente "esperando". Las de Forma 5 se excluyen a propósito: se
    // resuelven después, al cargar eventos.
    // ------------------------------------------------------------------

    public function cerrarCartasPendientes(int $idLiga, int $jornada): void
    {
        $codigosDeEventos = array_keys($this->efectosEventosPartido());

        CartaUsuario::where('id_liga', $idLiga)
            ->where('estado', 'jugada')
            ->where('jornada_efecto', '<=', $jornada)
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', 'Jugadas'))
            ->with('tipoCarta')
            ->get()
            ->reject(fn ($c) => in_array($c->tipoCarta->codigo_efecto, $codigosDeEventos, true))
            ->each(fn ($c) => $c->update(['estado' => 'resuelta_no_cumplida', 'puntos_generados' => 0]));
    }
}