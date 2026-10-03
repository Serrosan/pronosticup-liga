<?php

namespace App\Services\EfectosCartas;

use App\Models\ConfiguracionPuntos;

class BonusFijoPartido implements AjustadorPuntosPartido
{
    public function __construct(private int $bonus) {}

    public function calcular(int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array
    {
        return ['puntos' => $puntosBase + $this->bonus, 'nota' => null];
    }
}