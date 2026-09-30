<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CanjeCodigo;
use App\Models\CartaUsuario;
use App\Models\CategoriaCarta;
use App\Models\CodigoCanje;
use App\Models\RarezaProbabilidadLiga;
use App\Models\TipoCarta;
use App\Services\ProbabilidadesPorDefecto;
use App\Services\SorteoCartasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CanjeCodigoController extends Controller
{
    public function __construct(private SorteoCartasService $sorteo) {}

    public function canjear(Request $request)
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:100'],
        ]);

        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['message' => 'Necesitas estar en una liga con el modo Cartas activado para canjear esto.'], 422);
        }

        // Comparación sin distinguir mayúsculas/minúsculas ni espacios — que teclear
        // "assemble " o "ASSEMBLE" cuente igual que el código exacto guardado.
        $codigoNormalizado = mb_strtoupper(trim($validated['codigo']));

        $codigo = CodigoCanje::whereRaw('UPPER(TRIM(codigo)) = ?', [$codigoNormalizado])
            ->where('activo', true)
            ->first();

        if (! $codigo) {
            return response()->json(['message' => 'Ese código no existe o ya no está activo.'], 404);
        }

        if (! is_null($codigo->usos_maximos) && $codigo->canjes()->count() >= $codigo->usos_maximos) {
            return response()->json(['message' => 'Este código ya se ha agotado.'], 410);
        }

        $yaCanjeado = CanjeCodigo::where('id_codigo', $codigo->id)
            ->where('id_usuario', $request->user()->id)
            ->exists();

        if ($yaCanjeado) {
            return response()->json(['message' => 'Ya has canjeado este código antes — solo se puede una vez por persona.'], 409);
        }

        $tipoElegido = $codigo->tipo_premio === 'carta_especifica'
            ? $this->cartaEspecifica($codigo)
            : $this->cartaAlAzar($liga);

        if (! $tipoElegido) {
            return response()->json(['message' => 'La carta configurada para este código ya no está disponible — avisa al admin.'], 422);
        }

        $carta = DB::transaction(function () use ($codigo, $tipoElegido, $liga, $request) {
            $nuevaCarta = CartaUsuario::create([
                'id_usuario' => $request->user()->id,
                'id_liga' => $liga->id,
                'id_tipo_carta' => $tipoElegido->id,
                'jornada_obtenida' => CalendarioPartido::jornadaActualParaTemporada($liga->id_temporada),
                'obtenida_en' => now(),
                'revelada_en' => now(), // canjear ES el momento de revelar, sin sobre de por medio
                'origen' => 'manual',
                'estado' => 'en_mano',
            ]);

            CanjeCodigo::create([
                'id_codigo' => $codigo->id,
                'id_usuario' => $request->user()->id,
                'id_liga' => $liga->id,
                'canjeado_en' => now(),
            ]);

            return $nuevaCarta;
        });

        $carta->load('tipoCarta.categoria');

        return response()->json(['data' => $carta]);
    }

    private function cartaEspecifica(CodigoCanje $codigo): ?TipoCarta
    {
        return TipoCarta::where('id', $codigo->id_tipo_carta)->where('activa', true)->first();
    }

    private function cartaAlAzar($liga): ?TipoCarta
    {
        $categorias = CategoriaCarta::where('activa', true)->get();

        if ($categorias->isEmpty()) {
            return null;
        }

        $categoriaAlAzar = $categorias->random();

        $filas = RarezaProbabilidadLiga::where('id_liga', $liga->id)
            ->where('id_categoria', $categoriaAlAzar->id)
            ->get();

        $porcentajes = $filas->isNotEmpty()
            ? $filas->pluck('porcentaje', 'rareza')->toArray()
            : ProbabilidadesPorDefecto::RAREZA;

        return $this->sorteo->elegirCartaAlAzar($categoriaAlAzar->id, $porcentajes);
    }
}