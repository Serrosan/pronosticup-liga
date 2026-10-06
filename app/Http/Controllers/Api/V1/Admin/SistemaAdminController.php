<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\VisorErroresService;
use Illuminate\Http\Request;

/**
 * /admin/sistema — qué versión está desplegada y los últimos errores de la
 * aplicación, para no tener que entrar al servidor a mirarlos. Solo lectura.
 */
class SistemaAdminController extends Controller
{
    public function index(Request $request, VisorErroresService $visor)
    {
        $canales = config('logging.default') === 'stack'
            ? (array) config('logging.channels.stack.channels', [])
            : [config('logging.default')];

        // ¿Los registros van a un archivo de storage/logs? Si no (p. ej. solo a
        // la salida del contenedor), este visor no tiene nada que leer.
        $aArchivo = collect($canales)->contains(fn ($canal) => in_array(config("logging.channels.{$canal}.driver"), ['single', 'daily'], true));

        $cache = base_path('bootstrap/cache/config.php');
        $commit = config('despliegue.commit');

        $resultado = $visor->ultimos(storage_path('logs'), base_path(), 60, $request->boolean('avisos'));

        return response()->json(['data' => [
            'version' => [
                'commit' => $commit,
                'commit_corto' => $commit ? substr($commit, 0, 7) : null,
                'rama' => config('despliegue.rama'),
                // La caché de configuración se genera al arrancar el contenedor: su fecha es la del despliegue.
                'desplegado_en' => is_file($cache) ? date(DATE_ATOM, filemtime($cache)) : null,
                'entorno' => config('app.env'),
            ],
            'registros' => [
                'canales' => array_values($canales),
                'a_archivo' => $aArchivo,
                'archivos' => $resultado['archivos'],
            ],
            'errores' => $resultado['errores'],
        ]]);
    }
}
