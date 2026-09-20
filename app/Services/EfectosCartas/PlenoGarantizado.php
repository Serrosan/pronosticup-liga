<?php

namespace App\Services\EfectosCartas;

use App\Models\ConfiguracionPuntos;

class PlenoGarantizado implements AjustadorPuntosPartido
{
    public function calcular(int $puntosBase, string $tipoEventoReal, ConfiguracionPuntos $config): array
    {
        if ($tipoEventoReal === 'AciertoExacto') {
            return ['puntos' => $puntosBase, 'nota' => null];
        }

        return [
            'puntos' => $puntosBase + $config->puntos_exacto,
            'nota' => "Pleno Garantizado: +{$config->puntos_exacto}pt aunque el resultado real fue {$tipoEventoReal}",
        ];
    }
}