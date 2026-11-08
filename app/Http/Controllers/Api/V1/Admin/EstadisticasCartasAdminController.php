<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Liga;
use App\Services\ProbabilidadesPorDefecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Cartas → Estadísticas: cómo se comportan las cartas de una liga, para
 * decidir balances y cartas nuevas con datos (lo que antes sacábamos con scripts).
 */
class EstadisticasCartasAdminController extends Controller
{
    private const RAREZAS = ['Comun', 'PocoComun', 'Rara', 'Legendaria'];

    public function index(Request $request)
    {
        $ligas = Liga::where('tipo', 'ConExtras')->orderBy('id')->get(['id', 'nombre']);
        $idLiga = (int) ($request->query('id_liga') ?: $ligas->first()?->id);

        if (! $idLiga) {
            return response()->json(['data' => ['ligas' => [], 'liga' => null]]);
        }

        $base = DB::table('cartas_usuario as c')
            ->join('tipos_carta as t', 't.id', '=', 'c.id_tipo_carta')
            ->join('categorias_carta as k', 'k.id', '=', 't.id_categoria')
            ->where('c.id_liga', $idLiga);

        // Por tipo de carta
        $porTipo = (clone $base)
            ->selectRaw("t.id, t.nombre, t.rareza, t.codigo_efecto, t.activa, k.nombre as categoria,
                count(*) as salidas,
                sum(c.estado = 'en_mano') as en_mano,
                sum(c.estado = 'jugada') as jugada,
                sum(c.estado = 'resuelta_cumplida') as cumplida,
                sum(c.estado = 'resuelta_no_cumplida') as no_cumplida,
                sum(c.estado = 'descartada') as descartada,
                sum(c.estado like 'retirada%') as retirada,
                sum(case when c.estado in ('resuelta_cumplida','resuelta_no_cumplida') then coalesce(c.puntos_generados, 0) else 0 end) as puntos")
            ->groupBy('t.id', 't.nombre', 't.rareza', 't.codigo_efecto', 't.activa', 'k.nombre')
            ->get()
            ->map(function ($f) {
                $jugadas = (int) $f->jugada + (int) $f->cumplida + (int) $f->no_cumplida;
                $resueltas = (int) $f->cumplida + (int) $f->no_cumplida;

                return [
                    'id' => (int) $f->id, 'nombre' => $f->nombre, 'categoria' => $f->categoria, 'rareza' => $f->rareza,
                    'codigo' => $f->codigo_efecto, 'activa' => (bool) $f->activa,
                    'salidas' => (int) $f->salidas, 'en_mano' => (int) $f->en_mano, 'jugadas' => $jugadas,
                    'pendientes' => (int) $f->jugada, 'cumplidas' => (int) $f->cumplida, 'no_cumplidas' => (int) $f->no_cumplida,
                    'descartadas' => (int) $f->descartada, 'retiradas' => (int) $f->retirada,
                    'puntos' => (int) $f->puntos,
                    'uso' => $f->salidas ? round(100 * $jugadas / $f->salidas) : null,
                    'acierto' => $resueltas ? round(100 * $f->cumplida / $resueltas) : null,
                    'puntos_media' => $resueltas ? round($f->puntos / $resueltas, 1) : null,
                ];
            })
            ->sortByDesc('salidas')->values();

        // Rareza: lo configurado frente a lo que ha salido de verdad, por origen
        $real = (clone $base)->selectRaw('c.origen, t.rareza, count(*) as n')->groupBy('c.origen', 't.rareza')->get()->groupBy('origen');
        $porOrigen = $real->map(function ($filas, $origen) {
            $total = $filas->sum('n');
            $porRareza = $filas->pluck('n', 'rareza');

            return [
                'origen' => $origen,
                'total' => (int) $total,
                'reparto' => collect(self::RAREZAS)->mapWithKeys(fn ($r) => [$r => [
                    'n' => (int) ($porRareza[$r] ?? 0),
                    'pct' => $total ? round(100 * ($porRareza[$r] ?? 0) / $total, 1) : 0,
                ]])->all(),
            ];
        })->values();

        $config = DB::table('rarezas_probabilidad_liga')->where('id_liga', $idLiga)->get();
        $configurado = collect(self::RAREZAS)->mapWithKeys(fn ($r) => [
            $r => $config->isNotEmpty() ? round($config->where('rareza', $r)->avg('porcentaje') ?? 0, 1) : (ProbabilidadesPorDefecto::RAREZA[$r] ?? 0),
        ])->all();

        // Activas que nunca han salido en esta liga
        $nuncaSalidas = DB::table('tipos_carta as t')->join('categorias_carta as k', 'k.id', '=', 't.id_categoria')
            ->where('t.activa', true)
            ->whereNotExists(fn ($q) => $q->from('cartas_usuario')->whereColumn('cartas_usuario.id_tipo_carta', 't.id')->where('cartas_usuario.id_liga', $idLiga))
            ->orderByRaw("CASE t.rareza WHEN 'Legendaria' THEN 0 WHEN 'Rara' THEN 1 WHEN 'PocoComun' THEN 2 ELSE 3 END")
            ->get(['t.nombre', 't.rareza', 'k.nombre as categoria', 't.codigo_efecto as codigo']);

        // Cartas jugadas por jornada y categoría
        $porJornada = (clone $base)->whereNotNull('c.jugada_en')->whereNotNull('c.jornada_efecto')
            ->selectRaw('c.jornada_efecto as jornada, k.nombre as categoria, count(*) as n, sum(coalesce(c.puntos_generados, 0)) as puntos')
            ->groupBy('c.jornada_efecto', 'k.nombre')->orderBy('c.jornada_efecto')->get()
            ->groupBy('jornada')->map(fn ($filas, $jornada) => [
                'jornada' => (int) $jornada,
                'jugadas' => (int) $filas->where('categoria', 'Jugadas')->sum('n'),
                'faltas' => (int) $filas->where('categoria', 'Faltas')->sum('n'),
                'puntos' => (int) $filas->sum('puntos'),
            ])->values();

        $estados = (clone $base)->selectRaw('c.estado, count(*) as n')->groupBy('c.estado')->pluck('n', 'estado');

        return response()->json(['data' => [
            'ligas' => $ligas,
            'liga' => $ligas->firstWhere('id', $idLiga),
            'resumen' => [
                'salidas' => (int) $estados->sum(),
                'en_mano' => (int) ($estados['en_mano'] ?? 0),
                'jugadas' => (int) (($estados['jugada'] ?? 0) + ($estados['resuelta_cumplida'] ?? 0) + ($estados['resuelta_no_cumplida'] ?? 0)),
                'descartadas' => (int) ($estados['descartada'] ?? 0),
                'puntos' => (int) $porTipo->sum('puntos'),
                'jornadas' => $porJornada->count(),
            ],
            'configurado' => $configurado,
            'por_origen' => $porOrigen,
            'por_tipo' => $porTipo,
            'nunca_salidas' => $nuncaSalidas,
            'por_jornada' => $porJornada,
        ]]);
    }
}
