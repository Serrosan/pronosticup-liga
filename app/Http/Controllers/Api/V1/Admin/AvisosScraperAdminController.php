<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;

class AvisosScraperAdminController extends Controller
{
    public function index()
    {
        $ruta = storage_path('app/scraper/avisos.log');

        if (! file_exists($ruta)) {
            return response()->json(['data' => []]);
        }

        $contenido = trim(file_get_contents($ruta));

        if ($contenido === '') {
            return response()->json(['data' => []]);
        }

        // Cada ejecución del scraper empieza con una línea "=== fecha — jornada N (X partidos) ==="
        $bloques = preg_split('/(?=^=== )/m', $contenido);

        $ejecuciones = [];
        foreach ($bloques as $bloque) {
            $bloque = trim($bloque);
            if ($bloque === '' || ! preg_match('/^=== (.+?) ===\s*(.*)$/s', $bloque, $m)) {
                continue;
            }

            $cabecera = $m[1];
            $lineas = array_filter(array_map('trim', explode("\n", trim($m[2]))));

            $avisos = [];
            foreach ($lineas as $linea) {
                if (preg_match('/^\[(.+?)\]\s*(.+)$/', $linea, $lm)) {
                    $avisos[] = ['partido' => $lm[1], 'mensaje' => $lm[2]];
                }
            }

            if (! empty($avisos)) {
                $ejecuciones[] = ['cabecera' => $cabecera, 'total_avisos' => count($avisos), 'avisos' => $avisos];
            }
        }

        // La más reciente primero — se van añadiendo al final del archivo.
        return response()->json(['data' => array_reverse($ejecuciones)]);
    }
}