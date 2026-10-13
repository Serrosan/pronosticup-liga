<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Las imágenes y archivos subidos (disco "public": avatares, logos de liga,
 * adjuntos del chat…) en un .zip, para tenerlos también en local.
 * Admin → Sistema → Copia para local. Solo LEE.
 *
 * Se elige por carpetas de primer nivel. Solo se aceptan nombres que existan
 * de verdad en el disco: nada de rutas inventadas.
 */
class CopiaImagenesService
{
    public const RAIZ = '(sueltos)';

    private function disco()
    {
        return Storage::disk('public');
    }

    /** @return array<int,array{carpeta:string,archivos:int,bytes:int}> */
    public function carpetas(): array
    {
        $disco = $this->disco();
        $resultado = [];

        foreach ($disco->directories() as $carpeta) {
            $archivos = $this->archivosDe($carpeta);
            $resultado[] = [
                'carpeta' => $carpeta,
                'archivos' => count($archivos),
                'bytes' => array_sum(array_map(fn ($a) => $disco->size($a), $archivos)),
            ];
        }

        $sueltos = $this->archivosDe(self::RAIZ);
        if ($sueltos) {
            $resultado[] = [
                'carpeta' => self::RAIZ,
                'archivos' => count($sueltos),
                'bytes' => array_sum(array_map(fn ($a) => $disco->size($a), $sueltos)),
            ];
        }

        usort($resultado, fn ($a, $b) => strcmp($a['carpeta'], $b['carpeta']));

        return $resultado;
    }

    /**
     * Crea el .zip en un archivo temporal y devuelve su ruta (quien lo use
     * debe borrarlo después; el controlador lo hace al enviarlo).
     *
     * @param  string[]  $pedidas
     */
    public function generarZip(array $pedidas): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene la extensión zip de PHP.');
        }

        $validas = array_column($this->carpetas(), 'carpeta');
        $carpetas = array_values(array_intersect($pedidas, $validas));

        if (! $carpetas) {
            throw new RuntimeException('Ninguna de esas carpetas existe.');
        }

        $base = tempnam(sys_get_temp_dir(), 'imagenes-');
        @unlink($base);
        $ruta = $base.'.zip';
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el .zip.');
        }

        $disco = $this->disco();
        foreach ($carpetas as $carpeta) {
            foreach ($this->archivosDe($carpeta) as $archivo) {
                // Las imágenes ya van comprimidas: guardarlas tal cual es más rápido.
                $zip->addFile($disco->path($archivo), $archivo);
                $zip->setCompressionName($archivo, ZipArchive::CM_STORE);
            }
        }

        $zip->close();

        return $ruta;
    }

    /** @return string[] rutas relativas al disco */
    private function archivosDe(string $carpeta): array
    {
        $disco = $this->disco();

        $archivos = $carpeta === self::RAIZ ? $disco->files() : $disco->allFiles($carpeta);

        // Fuera ficheros ocultos (.gitignore y similares).
        return array_values(array_filter($archivos, fn ($a) => ! str_starts_with(basename($a), '.')));
    }
}
