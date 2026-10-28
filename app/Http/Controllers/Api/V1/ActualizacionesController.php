<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Actualizacion;

/** GET /actualizaciones — lo publicado: primero "próximamente", luego el historial. */
class ActualizacionesController extends Controller
{
    public function index()
    {
        $publicadas = Actualizacion::where('publicada', true)
            ->orderByRaw("CASE WHEN tipo = 'proximamente' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $publicadas,
            // Cambia al publicar, editar o retirar algo: así sale el "NUEVO" del menú.
            'marca' => $publicadas->count().'|'.optional($publicadas->max('updated_at'))->toIso8601String(),
        ]);
    }
}
