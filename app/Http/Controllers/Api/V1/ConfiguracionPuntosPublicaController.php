<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionPuntos;
use Illuminate\Http\Request;

class ConfiguracionPuntosPublicaController extends Controller
{
    public function show(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $config = ConfiguracionPuntos::paraLiga($liga->id);

        return response()->json(['data' => $config]);
    }
}