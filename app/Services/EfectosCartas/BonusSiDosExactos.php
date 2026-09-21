<?php

namespace App\Services\EfectosCartas;

use Illuminate\Support\Collection;

class BonusSiDosExactos implements BonoMultiPartidoJornada
{
    public function __construct(private int $bonus) {}

    public function evaluar(Collection $eventosDeLaJornadaDelUsuario): array
    {
        $exactos = $eventosDeLaJornadaDelUsuario->where('tipo_evento', 'AciertoExacto')->count();
        $cumplido = $exactos >= 2;

        return ['cumplido' => $cumplido, 'puntos' => $cumplido ? $this->bonus : 0];
    }
}