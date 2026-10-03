<?php

namespace App\Services\EfectosCartas;

use App\Models\CartaUsuario;
use Illuminate\Support\Collection;

class ProtegeElegido implements ProtectorBonusPleno
{
    public function partidosAProteger(CartaUsuario $carta, Collection $partidosFallidosDisponibles): Collection
    {
        return $partidosFallidosDisponibles->filter(fn ($idPartido) => $idPartido === $carta->id_partido)->values();
    }
}