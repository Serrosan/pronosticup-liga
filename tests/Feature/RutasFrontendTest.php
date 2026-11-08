<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Toda llamada a la API que hace el frontend tiene que tener su ruta en el servidor.
 * (El historial de Mis Cartas se quedó sin ruta y la pestaña salía vacía sin avisar.)
 *
 * Lee frontend/src buscando client.get('/api/v1/...'), client.post(`/api/v1/x/${id}`)…
 * Los trozos ${…} cuentan como "cualquier valor". Las rutas genéricas del admin
 * (AdminResourceTable, con ${resource}) no se pueden comprobar así y se saltan.
 */
class RutasFrontendTest extends TestCase
{
    public function test_todas_las_llamadas_del_frontend_tienen_ruta(): void
    {
        $carpeta = base_path('frontend/src');
        if (! is_dir($carpeta)) {
            $this->markTestSkipped('No está la carpeta frontend/src.');
        }

        $rutas = collect(Route::getRoutes()->getRoutes())->flatMap(fn ($r) => collect($r->methods())->map(fn ($m) => [$m, explode('/', trim($r->uri(), '/'))]));

        $faltan = [];
        $revisadas = 0;
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $fichero) {
            if (! preg_match('/\.(jsx?|tsx?)$/', $fichero->getFilename())) {
                continue;
            }
            $codigo = file_get_contents($fichero->getPathname());
            preg_match_all('/client\.(get|post|put|patch|delete)\(\s*([`\'"])(\/[^`\'"]*)\2/', $codigo, $llamadas, PREG_SET_ORDER);

            foreach ($llamadas as [, $metodo, , $url]) {
                if (str_contains($url, '${resource}')) {
                    continue;
                }
                $ruta = preg_replace('/\$\{[^}]*\}/', '{x}', $url);   // ${id} → {x}
                $ruta = preg_replace('/\{x\}(\{x\})+/', '{x}', $ruta);  // ${id}${extra} → {x}
                $ruta = strtok($ruta, '?');
                $segmentos = explode('/', trim($ruta, '/'));
                $metodo = strtoupper($metodo);
                $revisadas++;

                $existe = $rutas->contains(function ($r) use ($metodo, $segmentos) {
                    [$m, $partes] = $r;
                    if ($m !== $metodo || count($partes) !== count($segmentos)) {
                        return false;
                    }
                    foreach ($partes as $i => $parte) {
                        $suyo = $segmentos[$i];
                        if ($suyo !== $parte && ! str_starts_with($parte, '{') && ! str_contains($suyo, '{x}')) {
                            return false;
                        }
                    }

                    return true;
                });

                if (! $existe) {
                    $faltan[] = "{$metodo} {$url}  (".str_replace(base_path().'/', '', $fichero->getPathname()).')';
                }
            }
        }

        $this->assertGreaterThan(50, $revisadas, 'Se esperaban muchas llamadas: ¿ha cambiado cómo se llama a la API?');
        $this->assertSame([], array_values(array_unique($faltan)), "Llamadas del frontend sin ruta en el servidor:\n".implode("\n", array_unique($faltan)));
    }
}
