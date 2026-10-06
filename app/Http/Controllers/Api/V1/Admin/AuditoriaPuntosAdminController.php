<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;
use App\Services\AuditoriaPuntosService;
use Illuminate\Http\Request;

/**
 * POST /admin/cierre-jornada/{liga}/{jornada}/auditar — comprueba que los
 * puntos guardados de una jornada coinciden con lo que saldría al calcularlos
 * ahora. No guarda nada (ver AuditoriaPuntosService).
 */
class AuditoriaPuntosAdminController extends Controller
{
    public function auditar(Request $request, Liga $liga, int $jornada, AuditoriaPuntosService $auditoria)
    {
        return response()->json(['data' => $auditoria->auditar($request, $liga, $jornada)]);
    }
}
