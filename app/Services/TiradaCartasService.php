<?php

namespace App\Services;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\Liga;
use App\Models\RarezaProbabilidadLiga;

/**
 * Una "tirada": un sobre cerrado con una carta al azar, como en el reparto. Lo usan
 * el admin (dar una tirada a alguien) y la retirada de cartas con compensación.
 */
class TiradaCartasService
{
    public function __construct(private SorteoCartasService $sorteo) {}

    /**
     * @param  int|null  $idCategoria  null = categoría al azar
     * @param  string|null  $rareza  null = porcentajes de la liga; si no, esa rareza siempre
     */
    public function dar(Liga $liga, int $idUsuario, ?int $idCategoria = null, ?string $rareza = null, string $origen = 'manual'): ?CartaUsuario
    {
        $categorias = CategoriaCarta::where('activa', true)->get();
        $categoria = $idCategoria ? $categorias->firstWhere('id', $idCategoria) : ($categorias->isNotEmpty() ? $categorias->random() : null);

        if (! $categoria) {
            return null;
        }

        $tipo = $this->sorteo->elegirCartaAlAzar($categoria->id, $rareza ? [$rareza => 100] : $this->porcentajesDeLaLiga($liga->id, $categoria->id));

        if (! $tipo) {
            return null;
        }

        return CartaUsuario::create([
            'id_usuario' => $idUsuario,
            'id_liga' => $liga->id,
            'id_tipo_carta' => $tipo->id,
            'jornada_obtenida' => CalendarioPartido::jornadaActualParaTemporada($liga->id_temporada),
            'obtenida_en' => now(),
            'revelada_en' => null, // llega como sobre: se descubre al abrirlo
            'origen' => $origen,
            'estado' => 'en_mano',
        ]);
    }

    /** @return array<string,int> */
    public function porcentajesDeLaLiga(int $idLiga, int $idCategoria): array
    {
        $filas = RarezaProbabilidadLiga::where('id_liga', $idLiga)->where('id_categoria', $idCategoria)->get();

        return $filas->isNotEmpty() ? $filas->pluck('porcentaje', 'rareza')->map(fn ($p) => (int) $p)->all() : ProbabilidadesPorDefecto::RAREZA;
    }
}
