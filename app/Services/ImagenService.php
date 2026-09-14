<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ImagenService
{
    public static function comprimir(string $rutaCompleta): void
    {
        if (! str_ends_with(strtolower($rutaCompleta), '.png')) {
            return; // de momento solo comprimimos PNG, que es lo único que sube la app
        }

        try {
            Process::run(['pngquant', '--quality=65-85', '--force', '--ext', '.png', $rutaCompleta]);
            // Si el archivo no es un PNG válido de verdad, o pngquant no puede comprimirlo
            // sin perder demasiada calidad, simplemente deja el original intacto — nunca
            // debe romper la subida del usuario por esto.
        } catch (\Throwable $e) {
            Log::warning('No se pudo comprimir la imagen subida: '.$e->getMessage());
        }
    }
}