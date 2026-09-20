<?php

namespace App\Services\EfectosCartas;

use Illuminate\Support\Collection;

interface EfectoEventosPartido
{
    /**
     * @return array{cumplido: bool, puntos: int}
     */
    public function evaluar(Collection $eventosDelPartido): array;
}