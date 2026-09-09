<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Equipo;

class EquipoListaController extends Controller
{
    public function index()
    {
        $equipos = Equipo::orderBy('nombre')->get(['id', 'nombre', 'nombre_corto', 'escudo_url']);

        return response()->json(['data' => $equipos]);
    }
}