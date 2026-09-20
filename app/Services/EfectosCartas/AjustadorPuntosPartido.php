<?php

namespace App\Services\EfectosCartas;

use App\Models\ConfiguracionPuntos;

interface AjustadorPuntosPartido
{
    /**
     * @return array{puntos: int, nota: ?string}
     */
    public function calcular(int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array;
}