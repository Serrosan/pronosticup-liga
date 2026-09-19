<?php

namespace App\Services;

use App\Models\TipoCarta;
use Illuminate\Support\Facades\Log;

class SorteoCartasService
{
    public function elegirCartaAlAzar(int $idCategoria, array $porcentajes): ?TipoCarta
    {
        $rarezasDisponibles = $porcentajes;

        while (! empty($rarezasDisponibles)) {
            $rarezaElegida = $this->sortearRareza($rarezasDisponibles);

            $candidatas = TipoCarta::where('id_categoria', $idCategoria)
                ->where('rareza', $rarezaElegida)
                ->where('activa', true)
                ->get();

            if ($candidatas->isNotEmpty()) {
                $cartaElegida = $candidatas->random();

                Log::channel('cartas')->info('Carta sorteada', [
                    'categoria_id' => $idCategoria,
                    'porcentajes' => $porcentajes,
                    'rareza_salida' => $rarezaElegida,
                    'candidatas_de_esa_rareza' => $candidatas->pluck('nombre'),
                    'carta_elegida' => $cartaElegida->nombre,
                ]);

                return $cartaElegida;
            }

            Log::channel('cartas')->warning('Rareza sin cartas activas, se descarta y se vuelve a sortear', [
                'categoria_id' => $idCategoria,
                'rareza_vacia' => $rarezaElegida,
            ]);

            unset($rarezasDisponibles[$rarezaElegida]);
        }

        Log::channel('cartas')->error('Ninguna rareza de esta categoría tiene cartas activas', ['categoria_id' => $idCategoria]);

        return null;
    }

    public function sortearRareza(array $porcentajes): string
    {
        $total = array_sum($porcentajes);

        if ($total <= 0) {
            return array_key_first($porcentajes);
        }

        $tirada = mt_rand(1, $total);
        $acumulado = 0;

        foreach ($porcentajes as $rareza => $porcentaje) {
            $acumulado += $porcentaje;
            if ($tirada <= $acumulado) {
                return $rareza;
            }
        }

        return array_key_first($porcentajes);
    }
}