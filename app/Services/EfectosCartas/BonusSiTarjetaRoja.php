<?php

namespace App\Services\EfectosCartas;

use Illuminate\Support\Collection;

class BonusSiTarjetaRoja implements EfectoEventosPartido
{
    public function __construct(private int $bonus) {}

    public function evaluar(Collection $eventosDelPartido): array
    {
        $hayRoja = $eventosDelPartido->contains(fn ($e) => $e->tipo_evento === 'tarjeta_roja');

        return ['cumplido' => $hayRoja, 'puntos' => $hayRoja ? $this->bonus : 0];
    }
}