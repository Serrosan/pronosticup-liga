<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CopiaParaLocalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /admin/copia-local — descarga un .sql de toda la base de datos para
 * cargarlo en local con HeidiSQL. Solo superadmin (grupo admin). Queda en el log.
 */
class CopiaLocalAdminController extends Controller
{
    public function descargar(Request $request, CopiaParaLocalService $copia)
    {
        $datos = $request->validate([
            'correos_falsos' => ['required', 'boolean'],
            'password_de_prueba' => ['required', 'boolean'],
        ]);

        $correosFalsos = (bool) $datos['correos_falsos'];
        $passwordDePrueba = (bool) $datos['password_de_prueba'];
        $nombre = 'pronosticup-'.app()->environment().'-'.now()->format('Y-m-d_Hi').'.sql';

        Log::info('Copia para local descargada', [
            'admin' => $request->user()->id,
            'correos_falsos' => $correosFalsos,
            'password_de_prueba' => $passwordDePrueba,
        ]);

        return response()->streamDownload(function () use ($copia, $correosFalsos, $passwordDePrueba) {
            $copia->generar(function (string $trozo) {
                echo $trozo;
            }, $correosFalsos, $passwordDePrueba);
        }, $nombre, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
