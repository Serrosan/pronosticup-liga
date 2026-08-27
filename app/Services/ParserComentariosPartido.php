<?php

namespace App\Services;

class ParserComentariosPartido
{
    public function parse(string $texto): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);
        $eventos = [];
        $minutoActual = null;

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            if (preg_match("/^(\d+(?:\+\d+)?)'$/", $linea, $m)) {
                $minutoActual = $m[1];
                continue;
            }

            if (preg_match('/¡Gooooool!.*?\.\s*([A-ZÁÉÍÓÚÑ][^(]*?)\s*\(([^)]+)\)/u', $linea, $m)) {
                $evento = [
                    'minuto' => $minutoActual,
                    'tipo_evento' => 'gol',
                    'jugador_texto' => trim($m[1]),
                    'equipo_texto' => trim($m[2]),
                    'jugador_relacionado_texto' => null,
                    'texto_original' => $linea,
                ];
                if (preg_match('/Asistencia de ([A-ZÁÉÍÓÚÑ][a-záéíóúñ.]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ.]+){0,3})/u', $linea, $ma)) {
                    $evento['jugador_relacionado_texto'] = trim($ma[1]);
                }
                $eventos[] = $evento;
                continue;
            }

            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*ha visto tarjeta amarilla/u', $linea, $m)) {
                $eventos[] = [
                    'minuto' => $minutoActual, 'tipo_evento' => 'tarjeta_amarilla',
                    'jugador_texto' => trim($m[1]), 'equipo_texto' => trim($m[2]),
                    'jugador_relacionado_texto' => null, 'texto_original' => $linea,
                ];
                continue;
            }

            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*ha visto tarjeta roja/u', $linea, $m)) {
                $eventos[] = [
                    'minuto' => $minutoActual, 'tipo_evento' => 'tarjeta_roja',
                    'jugador_texto' => trim($m[1]), 'equipo_texto' => trim($m[2]),
                    'jugador_relacionado_texto' => null, 'texto_original' => $linea,
                ];
                continue;
            }

            if (preg_match('/^Cambio en (.+?), entra al campo (.+?) sustituyendo a (.+?)(?:\s+debido a una lesión)?\.?$/u', $linea, $m)) {
                $eventos[] = [
                    'minuto' => $minutoActual, 'tipo_evento' => 'sustitucion',
                    'jugador_texto' => trim($m[2]), 'equipo_texto' => trim($m[1]),
                    'jugador_relacionado_texto' => trim($m[3]), 'texto_original' => $linea,
                ];
                continue;
            }
        }

        return $eventos;
    }
}