<?php

namespace App\Services\EfectosCartas;

use Illuminate\Support\Collection;

interface BonoMultiPartidoJornada
{
    /**
     * @return array{cumplido: bool, puntos: int}
     */
    public function evaluar(Collection $eventosDeLaJornadaDelUsuario): array;
}