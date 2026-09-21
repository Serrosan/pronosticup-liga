<?php

namespace App\Services\EfectosCartas;

interface ComparadorMayoriaLiga
{
    /**
     * @param string $miSigno Tu propio resultado_1x2 (Local/Empate/Visitante) en ese partido
     * @param ?string $mayoriaLiga El signo mayoritario del resto de la liga, o null si nadie más pronosticó
     * @return array{puntos: int}
     */
    public function evaluar(string $miSigno, ?string $mayoriaLiga): array;
}