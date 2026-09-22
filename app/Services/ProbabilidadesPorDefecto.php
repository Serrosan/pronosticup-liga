<?php

namespace App\Services;

/**
 * Los porcentajes de rareza que se usan cuando una liga aún no tiene su
 * propia configuración guardada — antes vivían duplicados (literalmente
 * copiados) en 3 sitios distintos del código de Cartas.
 */
class ProbabilidadesPorDefecto
{
    public const RAREZA = ['Comun' => 65, 'PocoComun' => 25, 'Rara' => 8, 'Legendaria' => 2];

    public const TOP3 = [
        1 => ['Comun' => 35, 'PocoComun' => 35, 'Rara' => 22, 'Legendaria' => 8],
        2 => ['Comun' => 45, 'PocoComun' => 33, 'Rara' => 17, 'Legendaria' => 5],
        3 => ['Comun' => 55, 'PocoComun' => 30, 'Rara' => 12, 'Legendaria' => 3],
    ];
}