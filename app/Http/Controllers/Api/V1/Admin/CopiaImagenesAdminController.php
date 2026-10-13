<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CopiaImagenesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GET  /admin/copia-local/imagenes — qué carpetas hay y cuánto pesan.
 * POST /admin/copia-local/imagenes — .zip con las carpetas elegidas.
 * Solo superadmin (grupo admin). La descarga queda en el log.
 */
class CopiaImagenesAdminController extends Controller
{
    public function carpetas(CopiaImagenesService $copia)
    {
        return response()->json(['data' => $copia->carpetas()]);
    }

    public function descargar(Request $request, CopiaImagenesService $copia)
    {
        $datos = $request->validate([
            'carpetas' => ['required', 'array', 'min:1'],
            'carpetas.*' => ['required', 'string', 'max:200'],
        ]);

        try {
            $ruta = $copia->generarZip($datos['carpetas']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Log::info('Copia de imágenes para local descargada', ['admin' => $request->user()->id, 'carpetas' => $datos['carpetas']]);

        $nombre = 'pronosticup-imagenes-'.app()->environment().'-'.now()->format('Y-m-d_Hi').'.zip';

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store'])
            ->deleteFileAfterSend();
    }
}
