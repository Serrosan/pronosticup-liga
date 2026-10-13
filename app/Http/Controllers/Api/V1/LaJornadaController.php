<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LaJornadaService;
use Illuminate\Http\Request;

/** "La jornada en tu liga": qué ha puesto cada uno, cuando ya no se puede cambiar. */
class LaJornadaController extends Controller
{
    public function show(Request $request, int $jornada, LaJornadaService $servicio)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($jornada < 1 || $jornada > 38) {
            return response()->json(['message' => 'Esa jornada no existe.'], 404);
        }

        return response()->json(['data' => $servicio->resumen($liga, $jornada, $request->user())]);
    }
}
