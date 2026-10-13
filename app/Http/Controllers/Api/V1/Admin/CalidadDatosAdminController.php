<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CalidadDatosService;

/**
 * GET /admin/calidad-datos — problemas en los datos de jugadores y plantillas
 * de la temporada en curso. Solo lectura.
 */
class CalidadDatosAdminController extends Controller
{
    public function index(CalidadDatosService $calidad)
    {
        return response()->json(['data' => $calidad->revisar()]);
    }
}
