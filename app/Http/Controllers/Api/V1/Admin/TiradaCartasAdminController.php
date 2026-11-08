<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartaUsuario;
use App\Models\Liga;
use App\Models\TipoCarta;
use App\Models\User;
use App\Notifications\CartaRecibidaManual;
use App\Notifications\CartaRetiradaCompensada;
use App\Services\TiradaCartasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Cartas:
 * - dar una tirada (sobre al azar) a un jugador concreto;
 * - retirar un tipo de carta del juego dando, si se quiere, una tirada por cada carta retirada.
 */
class TiradaCartasAdminController extends Controller
{
    private const RAREZAS = 'in:Comun,PocoComun,Rara,Legendaria';

    public function __construct(private TiradaCartasService $tiradas) {}

    /** POST /admin/cartas-usuario/tirada */
    public function tirada(Request $request)
    {
        $datos = $request->validate([
            'id_liga' => ['required', 'exists:ligas,id'],
            'id_usuario' => ['required', 'exists:users,id'],
            'id_categoria' => ['nullable', 'exists:categorias_carta,id'],
            'rareza' => ['nullable', self::RAREZAS],
        ]);

        $liga = Liga::findOrFail($datos['id_liga']);
        if (! $liga->usuarios()->where('id_usuario', $datos['id_usuario'])->exists()) {
            return response()->json(['message' => 'Ese usuario no pertenece a esa liga.'], 422);
        }

        $carta = $this->tiradas->dar($liga, (int) $datos['id_usuario'], $datos['id_categoria'] ?? null, $datos['rareza'] ?? null);
        if (! $carta) {
            return response()->json(['message' => 'No hay ninguna carta activa con esa categoría y rareza para sortear.'], 422);
        }

        User::find($datos['id_usuario'])?->notify(new CartaRecibidaManual('', sobre: true));
        $carta->load('tipoCarta.categoria');

        return response()->json([
            'data' => $carta,
            'message' => "Sobre entregado (le ha tocado: {$carta->tipoCarta->nombre} · {$carta->tipoCarta->rareza}). Lo descubrirá al abrirlo.",
        ]);
    }

    /** GET /admin/tipos-carta/{tipoCarta}/retirada — quién la tiene en la mano ahora mismo. */
    public function previaRetirada(TipoCarta $tipoCarta)
    {
        $cartas = CartaUsuario::where('id_tipo_carta', $tipoCarta->id)->where('estado', 'en_mano')->with('usuario')->get();
        $ligas = Liga::whereIn('id', $cartas->pluck('id_liga')->unique())->pluck('nombre', 'id');

        return response()->json(['data' => [
            'carta' => $tipoCarta->nombre,
            'activa' => (bool) $tipoCarta->activa,
            'en_mano' => $cartas->count(),
            'jugadores' => $cartas->groupBy(fn ($c) => $c->id_usuario.'-'.$c->id_liga)->map(fn ($grupo) => [
                'nombre' => $grupo->first()->usuario?->nombre_visible ?? $grupo->first()->usuario?->name ?? '?',
                'liga' => $ligas[$grupo->first()->id_liga] ?? null,
                'cartas' => $grupo->count(),
            ])->values(),
        ]]);
    }

    /** POST /admin/tipos-carta/{tipoCarta}/retirar */
    public function retirar(Request $request, TipoCarta $tipoCarta)
    {
        $datos = $request->validate([
            'compensar' => ['required', 'boolean'],
            'rareza' => ['nullable', self::RAREZAS],
        ]);

        $porUsuario = [];
        $sinCompensar = 0;
        $retiradas = 0;

        DB::transaction(function () use ($tipoCarta, $datos, &$porUsuario, &$sinCompensar, &$retiradas) {
            $tipoCarta->update(['activa' => false]);

            $cartas = CartaUsuario::where('id_tipo_carta', $tipoCarta->id)->where('estado', 'en_mano')->lockForUpdate()->get();
            $ligas = Liga::whereIn('id', $cartas->pluck('id_liga')->unique())->get()->keyBy('id');

            foreach ($cartas as $carta) {
                $carta->update(['estado' => 'retirada_por_catalogo']);
                $retiradas++;

                if ($datos['compensar']) {
                    // Ya desactivada, la carta retirada no puede volver a salir en la tirada.
                    $nueva = $this->tiradas->dar($ligas[$carta->id_liga], $carta->id_usuario, null, $datos['rareza'] ?? null);
                    $nueva ? $porUsuario[$carta->id_usuario] = ($porUsuario[$carta->id_usuario] ?? 0) + 1 : $sinCompensar++;
                }
            }
        });

        foreach ($porUsuario as $idUsuario => $sobres) {
            User::find($idUsuario)?->notify(new CartaRetiradaCompensada($tipoCarta->nombre, $sobres));
        }

        $mensaje = "«{$tipoCarta->nombre}» retirada del juego ({$retiradas} en mano)."
            .($datos['compensar'] ? ' Sobres dados: '.array_sum($porUsuario).' a '.count($porUsuario).' jugador(es).' : ' Sin compensación.')
            .($sinCompensar ? " ⚠️ {$sinCompensar} no se pudieron compensar (no había cartas para sortear)." : '');

        return response()->json(['message' => $mensaje]);
    }
}
