<?php

/**
 * Genera los iconos de la app instalable a partir de frontend/public/logo.png.
 *
 *   ./vendor/bin/sail php scripts/generar_iconos.php
 *
 * Crea en frontend/public/icons/:
 *   icon-192.png, icon-512.png       (Android / Chrome)
 *   icon-maskable-512.png            (Android con icono adaptable: logo más pequeño, con margen)
 *   apple-touch-icon.png (180x180)   (iPhone / iPad; sin transparencias, que iOS las pinta de negro)
 *
 * Fondo: el navy de la app (#0E1B2B). Solo usa GD, que ya está en el contenedor.
 */

$publico = __DIR__.'/../frontend/public';
$origen = $publico.'/logo.png';
$destino = $publico.'/icons';

if (! extension_loaded('gd')) {
    fwrite(STDERR, "✗ Falta la extensión GD de PHP.\n");
    exit(1);
}
if (! is_file($origen)) {
    fwrite(STDERR, "✗ No encuentro {$origen}\n");
    exit(1);
}

$logo = imagecreatefrompng($origen);
if (! $logo) {
    fwrite(STDERR, "✗ No he podido leer logo.png como PNG.\n");
    exit(1);
}
imagealphablending($logo, true);

// Recorta los bordes totalmente transparentes del logo, para que ocupe lo que debe.
[$x0, $y0, $x1, $y1] = [imagesx($logo), imagesy($logo), 0, 0];
for ($y = 0; $y < imagesy($logo); $y += 2) {
    for ($x = 0; $x < imagesx($logo); $x += 2) {
        $alfa = (imagecolorat($logo, $x, $y) >> 24) & 0x7F;
        if ($alfa < 120) {
            $x0 = min($x0, $x); $y0 = min($y0, $y);
            $x1 = max($x1, $x); $y1 = max($y1, $y);
        }
    }
}
if ($x1 <= $x0 || $y1 <= $y0) {
    [$x0, $y0, $x1, $y1] = [0, 0, imagesx($logo) - 1, imagesy($logo) - 1];
}
$anchoLogo = $x1 - $x0 + 1;
$altoLogo = $y1 - $y0 + 1;

/**
 * @param  float  $ocupa  qué parte del lado ocupa el logo (0.6 = 60 %)
 */
function icono($logo, int $x0, int $y0, int $anchoLogo, int $altoLogo, int $lado, float $ocupa, string $ruta): void
{
    $lienzo = imagecreatetruecolor($lado, $lado);
    imagealphablending($lienzo, true);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 0x0E, 0x1B, 0x2B));

    $escala = ($lado * $ocupa) / max($anchoLogo, $altoLogo);
    $ancho = (int) round($anchoLogo * $escala);
    $alto = (int) round($altoLogo * $escala);

    imagecopyresampled($lienzo, $logo, (int) (($lado - $ancho) / 2), (int) (($lado - $alto) / 2), $x0, $y0, $ancho, $alto, $anchoLogo, $altoLogo);
    imagepng($lienzo, $ruta, 9);
    imagedestroy($lienzo);

    printf("✓ %s (%dx%d, %d KB)\n", basename($ruta), $lado, $lado, (int) ceil(filesize($ruta) / 1024));
}

if (! is_dir($destino) && ! mkdir($destino, 0755, true)) {
    fwrite(STDERR, "✗ No puedo crear {$destino}\n");
    exit(1);
}

icono($logo, $x0, $y0, $anchoLogo, $altoLogo, 192, 0.80, $destino.'/icon-192.png');
icono($logo, $x0, $y0, $anchoLogo, $altoLogo, 512, 0.80, $destino.'/icon-512.png');
icono($logo, $x0, $y0, $anchoLogo, $altoLogo, 512, 0.58, $destino.'/icon-maskable-512.png');
icono($logo, $x0, $y0, $anchoLogo, $altoLogo, 180, 0.80, $destino.'/apple-touch-icon.png');

echo "Listo. Míralos en frontend/public/icons/ antes de subirlos.\n";
