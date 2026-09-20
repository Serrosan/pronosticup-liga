<?php

namespace App\Services\EfectosCartas;

use App\Models\CartaUsuario;
use Illuminate\Support\Collection;

interface ProtectorBonusPleno
{
    /**
     * Dado el conjunto de partidos que el usuario falló esta jornada, devuelve
     * cuáles de ellos protege esta carta (para que cuenten como acierto de cara
     * al bonus de pleno, aunque en el historial sigan como Fallo real).
     *
     * @return Collection<int> ids de partidos protegidos
     */
    public function partidosAProteger(CartaUsuario $carta, Collection $partidosFallidosDisponibles): Collection;
}