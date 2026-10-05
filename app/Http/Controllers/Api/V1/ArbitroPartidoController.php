<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Services\EstadisticasArbitroService;

/**
 * GET /api/v1/partidos/{partido}/arbitro — las tarjetas del árbitro de ese
 * partido en lo que va de temporada. Aparte del detalle del partido a
 * propósito: es un dato de adorno y no debe poder romper esa pantalla.
 */
class ArbitroPartidoController extends Controller
{
    public function show(CalendarioPartido $partido, EstadisticasArbitroService $servicio)
    {
        return response()->json(['data' => $servicio->delPartido($partido)]);
    }
}
