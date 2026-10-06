<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LogrosSecretos;
use Illuminate\Http\Request;

/**
 * GET /api/v1/logros-secretos — la lista de logros secretos tal como la ve el
 * jugador: solo títulos y estado. Cómo se consigue cada uno no viaja nunca al
 * navegador.
 */
class LogrosSecretosController extends Controller
{
    public function index(Request $request, LogrosSecretos $logros)
    {
        $liga = $request->user()->ligaActiva;

        return response()->json([
            'data' => $logros->lista(),
            // El premio es una carta: solo tiene sentido contarlo en ligas con cartas.
            'meta' => ['con_cartas' => $liga?->tipo === 'ConExtras'],
        ]);
    }
}
