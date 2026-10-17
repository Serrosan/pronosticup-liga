<?php

/**
 * Genera el icono pequeño de la barra de estado de Android para los avisos:
 * frontend/public/icons/badge-96.png — la silueta del logo en blanco sobre
 * transparente (Android solo usa la forma, no los colores).
 *
 *   ./vendor/bin/sail php scripts/generar_badge.php
 */

$origen = __DIR__.'/../frontend/public/logo.png';
$destino = __DIR__.'/../frontend/public/icons/badge-96.png';
$lado = 96;

if (! extension_loaded('gd') || ! is_file($origen) || ! ($logo = imagecreatefrompng($origen))) {
    fwrite(STDERR, "✗ Necesito GD y frontend/public/logo.png\n");
    exit(1);
}

// Mismo recorte de bordes transparentes que generar_iconos.php.
[$x0, $y0, $x1, $y1] = [imagesx($logo), imagesy($logo), 0, 0];
for ($y = 0; $y < imagesy($logo); $y++) {
    for ($x = 0; $x < imagesx($logo); $x++) {
        if ((((imagecolorat($logo, $x, $y) >> 24) & 0x7F)) < 120) {
            $x0 = min($x0, $x); $y0 = min($y0, $y); $x1 = max($x1, $x); $y1 = max($y1, $y);
        }
    }
}
if ($x1 <= $x0 || $y1 <= $y0) {
    [$x0, $y0, $x1, $y1] = [0, 0, imagesx($logo) - 1, imagesy($logo) - 1];
}
$ancho = $x1 - $x0 + 1;
$alto = $y1 - $y0 + 1;

$escalado = imagecreatetruecolor($lado, $lado);
imagealphablending($escalado, false);
imagesavealpha($escalado, true);
imagefill($escalado, 0, 0, imagecolorallocatealpha($escalado, 0, 0, 0, 127));
$escala = ($lado * 0.9) / max($ancho, $alto);
$w = (int) round($ancho * $escala);
$h = (int) round($alto * $escala);
imagecopyresampled($escalado, $logo, (int) (($lado - $w) / 2), (int) (($lado - $h) / 2), $x0, $y0, $w, $h, $ancho, $alto);

$badge = imagecreatetruecolor($lado, $lado);
imagealphablending($badge, false);
imagesavealpha($badge, true);
$opacos = 0;
for ($y = 0; $y < $lado; $y++) {
    for ($x = 0; $x < $lado; $x++) {
        $alfa = (imagecolorat($escalado, $x, $y) >> 24) & 0x7F;
        $opacos += $alfa < 64 ? 1 : 0;
        imagesetpixel($badge, $x, $y, imagecolorallocatealpha($badge, 255, 255, 255, $alfa));
    }
}
imagepng($badge, $destino, 9);

$porcentaje = round($opacos * 100 / ($lado * $lado));
echo "✓ badge-96.png ({$porcentaje} % de la superficie es silueta)\n";
if ($porcentaje > 85) {
    echo "⚠️  El logo no tiene fondo transparente: el icono de la barra de estado saldrá como un cuadrado blanco.\n";
}
