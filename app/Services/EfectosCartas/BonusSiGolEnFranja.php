<?php

namespace App\Services\EfectosCartas;

use Illuminate\Support\Collection;

class BonusSiGolEnFranja implements EfectoEventosPartido
{
    public function __construct(private int $desde, private int $hasta, private int $bonus) {}

    public function evaluar(Collection $eventosDelPartido): array
    {
        $hayGolEnFranja = $eventosDelPartido
            ->whereIn('tipo_evento', ['gol', 'gol_en_propia'])
            ->contains(fn ($e) => $e->minuto >= $this->desde && $e->minuto <= $this->hasta);

        return ['cumplido' => $hayGolEnFranja, 'puntos' => $hayGolEnFranja ? $this->bonus : 0];
    }
}