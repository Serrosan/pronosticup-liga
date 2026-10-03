<?php

namespace App\Services\EfectosCartas;

use App\Models\ConfiguracionPuntos;

class BonusSiExacto implements AjustadorPuntosPartido
{
    public function __construct(private int $bonus) {}

    public function calcular(int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array
    {
        if ($tipoEventoReal !== 'AciertoExacto') {
            return ['puntos' => $puntosBase, 'nota' => null];
        }

        return ['puntos' => $puntosBase + $this->bonus, 'nota' => null];
    }
}