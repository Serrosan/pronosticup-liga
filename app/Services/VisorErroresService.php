<?php

namespace App\Services;

/**
 * Lee los registros de Laravel (storage/logs/laravel*.log) y devuelve los
 * últimos errores, agrupando los que son el mismo fallo repetido.
 *
 * Solo lee, y solo el final de cada archivo: nunca carga un registro entero
 * en memoria, por grande que sea.
 */
class VisorErroresService
{
    private const BYTES_POR_ARCHIVO = 1_500_000;
    private const NIVELES_ERROR = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    private const CABECERA = '\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\] ([\w-]+)\.([A-Z]+): ';

    /**
     * @return array{errores: array<int,array<string,mixed>>, archivos: array<int,array{nombre:string,bytes:int}>}
     */
    public function ultimos(string $directorio, string $raizProyecto, int $limite = 60, bool $conAvisos = false): array
    {
        $rutas = glob(rtrim($directorio, '/').'/laravel*.log') ?: [];
        usort($rutas, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $niveles = $conAvisos ? [...self::NIVELES_ERROR, 'WARNING'] : self::NIVELES_ERROR;
        $grupos = [];
        $archivos = [];

        foreach ($rutas as $ruta) {
            $archivos[] = ['nombre' => basename($ruta), 'bytes' => (int) filesize($ruta)];

            foreach (array_reverse($this->entradas($ruta)) as $entrada) {
                if (! in_array($entrada['nivel'], $niveles, true)) {
                    continue;
                }

                $entrada['archivo'] = $entrada['archivo'] ? ltrim(str_replace(rtrim($raizProyecto, '/'), '', $entrada['archivo']), '/') : null;
                $firma = md5($entrada['nivel'].'|'.$entrada['clase'].'|'.$entrada['archivo'].'|'.$entrada['linea'].'|'.preg_replace('/\d+/', '#', $entrada['mensaje']));

                if (isset($grupos[$firma])) {
                    $grupos[$firma]['veces']++;
                    $grupos[$firma]['primera_vez'] = $entrada['cuando']; // se recorre de nuevo a viejo
                } elseif (count($grupos) < $limite) {
                    $grupos[$firma] = $entrada + ['veces' => 1, 'primera_vez' => $entrada['cuando']];
                }
            }
        }

        return ['errores' => array_values($grupos), 'archivos' => $archivos];
    }

    /** Las entradas del final de un archivo, de la más antigua a la más reciente. */
    private function entradas(string $ruta): array
    {
        $tamano = (int) filesize($ruta);
        $puntero = @fopen($ruta, 'rb');

        if (! $puntero || $tamano === 0) {
            return [];
        }

        $desde = max(0, $tamano - self::BYTES_POR_ARCHIVO);
        fseek($puntero, $desde);
        $texto = (string) fread($puntero, self::BYTES_POR_ARCHIVO);
        fclose($puntero);

        $bloques = preg_split('/^(?='.self::CABECERA.')/m', $texto) ?: [];
        if ($desde > 0) {
            array_shift($bloques); // el primer trozo puede empezar a mitad de una entrada
        }

        $entradas = [];
        foreach ($bloques as $bloque) {
            if (! preg_match('/^'.self::CABECERA.'(.*)$/s', $bloque, $m)) {
                continue;
            }

            [, $cuando, , $nivel, $resto] = $m;
            $primeraLinea = strtok($resto, "\n") ?: '';
            $mensaje = trim(preg_replace('/ \{"exception":.*$/s', '', $primeraLinea) ?? $primeraLinea);

            $clase = $archivo = $linea = null;
            if (preg_match('/\[object\] \(([\w\\\\]+)\(code: [^)]*\): (.*?) at (\S+?):(\d+)\)/s', $resto, $e)) {
                $clase = $e[1];
                $archivo = $e[3];
                $linea = (int) $e[4];
                if ($mensaje === '') {
                    $mensaje = trim($e[2]);
                }
            }

            preg_match_all('/^#\d+ .*$/m', $resto, $traza);

            $entradas[] = [
                'cuando' => $this->fechaIso($cuando),
                'nivel' => $nivel,
                'mensaje' => mb_substr($mensaje, 0, 600),
                'clase' => $clase,
                'archivo' => $archivo,
                'linea' => $linea,
                'traza' => array_slice($traza[0] ?? [], 0, 12),
            ];
        }

        return $entradas;
    }

    private function fechaIso(string $texto): ?string
    {
        try {
            return (new \DateTimeImmutable($texto))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
