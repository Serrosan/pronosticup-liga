<?php

namespace App\Services;

use App\Models\CartaUsuario;
use App\Models\ConfiguracionPuntos;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Services\EfectosCartas\BonoMultiPartidoJornada;
use App\Services\EfectosCartas\BonusFijoPartido;
use App\Services\EfectosCartas\BonusSiDosExactos;
use App\Services\EfectosCartas\BonusSiExacto;
use App\Services\EfectosCartas\BonusSiTarjetaRoja;
use App\Services\EfectosCartas\Doblete;
use App\Services\EfectosCartas\PlenoGarantizado;
use App\Services\EfectosCartas\ProtegeAutomatico;
use App\Services\EfectosCartas\ProtegeElegido;
use Illuminate\Support\Collection;

class MotorEfectosCartas
{
    // ------------------------------------------------------------------
    // FORMA 1 — ajusta los puntos de UN partido elegido de antemano.
    // Se resuelve al calcular los puntos de cada pronóstico (cierre/recalculo).
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

    /**
     * @return array{puntos: int, nota: ?string}
     */
    public function ajustarPuntosPartido(int $idLiga, int $idUsuario, int $idPartido, int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array
    {
        $carta = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuario)
            ->where('id_partido', $idPartido)
            ->where('estado', 'jugada')
            ->with('tipoCarta')
            ->first();

        if (! $carta) {
            return ['puntos' => $puntosBase, 'nota' => null];
        }

        $ajustadores = $this->ajustadoresPartido();
        $codigoEfecto = $carta->tipoCarta->codigo_efecto;

        if (! isset($ajustadores[$codigoEfecto])) {
            return ['puntos' => $puntosBase, 'nota' => null];
        }

        $resultado = $ajustadores[$codigoEfecto]->calcular($puntosBase, $tipoEventoReal, $config);

        $carta->update([
            'estado' => 'resuelta_cumplida',
            'puntos_generados' => $resultado['puntos'] - $puntosBase,
        ]);

        return $resultado;
    }

    // ------------------------------------------------------------------
    // FORMA 4 — modifica qué cuenta como "acierto" para el bonus de pleno.
    // Se resuelve una vez por usuario, tras conocer TODOS los fallos de la
    // jornada (no partido a partido como la Forma 1).
    // ------------------------------------------------------------------

    private function protectoresBonus(): array
    {
        return [
            'JUG-PCOM-AMULETO' => new ProtegeElegido(),
            'JUG-RAR-AMULETO' => new ProtegeAutomatico(1),
            'JUG-LEG-AMULETO' => new ProtegeAutomatico(2),
        ];
    }

    /**
     * Devuelve el número de aciertos "efectivos" a usar para calcular el bonus de
     * pleno — el real, más los partidos fallados que algún Amuleto jugado protege.
     * El historial de esos partidos sigue mostrando Fallo real, esto solo afecta
     * al cálculo del bonus.
     */
    public function ajustarAciertosParaBonus(int $idLiga, int $idUsuario, int $jornada, int $aciertosReales, Collection $partidosFallidos): int
    {
        $registro = $this->protectoresBonus();

        $cartasJugadas = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuario)
            ->where('estado', 'jugada')
            ->with('tipoCarta')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $protegidos = collect();

        foreach ($cartasJugadas as $carta) {
            $disponibles = $partidosFallidos->diff($protegidos);
            $protector = $registro[$carta->tipoCarta->codigo_efecto];
            $protegidosPorEstaCarta = $protector->partidosAProteger($carta, $disponibles);

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
    // FORMA 3 — bono sobre varios partidos de la jornada, SIN elegir ninguno
    // de antemano. Se juega "en genérico" para toda la jornada siguiente, y
    // se resuelve al cerrar (junto con el resto de puntos), mirando el
    // conjunto de resultados ya calculados de esa jornada.
    // ------------------------------------------------------------------

    private function bonosMultiPartido(): array
    {
        return [
            'JUG-LEG-CRACK' => new BonusSiDosExactos(4),
        ];
    }

    /**
     * Usado por MisCartasController al jugar una carta, para saber si debe
     * pedir que se elija un partido (Formas 1 y 4) o jugarse "en genérico"
     * sobre toda la jornada (Forma 3).
     */
    public function requiereEleccionDePartido(string $codigoEfecto): bool
    {
        return ! array_key_exists($codigoEfecto, $this->bonosMultiPartido());
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

        $cartasJugadas = CartaUsuario::where('id_liga', $idLiga)
            ->where('jornada_efecto', $jornada)
            ->where('estado', 'jugada')
            ->with('tipoCarta')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $puntosPorUsuario = [];

        foreach ($cartasJugadas as $carta) {
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
    // FORMA 5 — depende de eventos del partido (tarjetas/goles), no del
    // resultado. Se resuelve al recalcular eventos, NUNCA al cerrar jornada.
    // ------------------------------------------------------------------

    private function efectosEventosPartido(): array
    {
        return [
            'JUG-COM-AMIGOARBITRO' => new BonusSiTarjetaRoja(2),
        ];
    }

    /**
     * Idempotente a propósito — se puede llamar varias veces según se van
     * cargando eventos de más partidos, sin duplicar puntos nunca.
     */
    public function resolverEfectosDeEventos(int $idLiga, int $jornada, Collection $idsPartidos): int
    {
        EventoPuntos::where('id_liga', $idLiga)
            ->where('jornada', $jornada)
            ->where('tipo_evento', 'CartaEventoPartido')
            ->delete();

        $registro = $this->efectosEventosPartido();

        $cartasJugadas = CartaUsuario::whereIn('id_partido', $idsPartidos)
            ->where('id_liga', $idLiga)
            ->where('estado', 'jugada')
            ->with('tipoCarta')
            ->get()
            ->filter(fn ($c) => isset($registro[$c->tipoCarta->codigo_efecto]));

        $creados = 0;

        foreach ($cartasJugadas as $carta) {
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
            }
        }

        return $creados;
    }
}