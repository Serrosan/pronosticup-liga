<?php

namespace App\Services\EfectosCartas;

use App\Models\CartaUsuario;
use Illuminate\Support\Collection;

class ProtegeAutomatico implements ProtectorBonusPleno
{
    public function __construct(private int $cantidad) {}

    public function partidosAProteger(CartaUsuario $carta, Collection $partidosFallidosDisponibles): Collection
    {
        return $partidosFallidosDisponibles->take($this->cantidad)->values();
    }
}