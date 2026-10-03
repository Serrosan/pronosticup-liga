<?php

namespace App\Services\EfectosCartas;

class BonusSiNoCoincideMayoria implements ComparadorMayoriaLiga
{
    public function __construct(private int $bonus) {}

    public function evaluar(string $miSigno, ?string $mayoriaLiga): array
    {
        if ($mayoriaLiga === null) {
            return ['puntos' => 0];
        }

        return ['puntos' => $miSigno !== $mayoriaLiga ? $this->bonus : 0];
    }
}