<?php

namespace App\Services;

use App\Models\CartaUsuario;
use App\Models\ConfiguracionPuntos;
use App\Services\EfectosCartas\BonusFijoPartido;
use App\Services\EfectosCartas\BonusSiExacto;
use App\Services\EfectosCartas\Doblete;
use App\Services\EfectosCartas\PlenoGarantizado;

class MotorEfectosCartas
{
    /**
     * Registro de Forma 1: cartas que ajustan los puntos de UN partido elegido de
     * antemano. Añadir una carta nueva de esta forma es 1 línea aquí — nada más
     * cambia, ni JornadaController ni ninguna otra carta se ven afectados.
     */
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
     * Si el usuario tiene una carta "jugada" de Forma 1 sobre este partido concreto,
     * ajusta sus puntos y la marca como resuelta. Si no hay ninguna (o es de otra
     * forma sin lógica todavía), devuelve los puntos base intactos.
     *
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
}