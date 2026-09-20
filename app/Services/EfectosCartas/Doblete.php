<?php

namespace App\Services\EfectosCartas;

use App\Models\ConfiguracionPuntos;

class Doblete implements AjustadorPuntosPartido
{
    public function calcular(int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array
    {
        return ['puntos' => $puntosBase * 2, 'nota' => null];
    }
}