<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BonusTop3ProbabilidadLiga;
use App\Models\CategoriaCarta;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\ConfiguracionCartasLiga;
use App\Models\EventoPuntos;
use App\Models\RarezaProbabilidadLiga;
use App\Models\User;
use App\Notifications\CartasRepartidas;
use App\Services\ProbabilidadesPorDefecto;
use App\Services\SorteoCartasService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartaRepartoController extends Controller
{
    public function __construct(private SorteoCartasService $sorteo) {}

    public function repartirJornada(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esAdmin = $liga->usuarios()
            ->where('id_usuario', $request->user()->id)
            ->wherePivot('rol', 'Admin')
            ->exists();

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo el admin de la liga puede repartir cartas.'], 403);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['message' => 'Esta liga no tiene el modo Cartas activado.'], 422);
        }

        // Las cartas no son retroactivas: una jornada que ya había empezado cuando la
        // liga recibió sus primeras cartas (o una anterior con un aplazado) no tiene reparto.
        $primeraConCartas = app(\App\Services\CierreJornadaService::class)->primeraJornadaConCartas($liga);
        if ($primeraConCartas !== null && $jornada < $primeraConCartas) {
            return response()->json(['message' => "Las cartas de esta liga empiezan en la jornada {$primeraConCartas}: la jornada {$jornada} no tiene reparto."], 422);
        }

        $yaCerrada = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->exists();
        if (! $yaCerrada) {
            return response()->json(['message' => 'Cierra la jornada primero.'], 422);
        }

        // Solo cuenta un reparto semanal anterior. Las cartas dadas a mano o por código
        // también llevan jornada_obtenida, y antes bloqueaban el reparto de esa jornada.
        $yaRepartida = CartaUsuario::where('id_liga', $liga->id)
            ->where('jornada_obtenida', $jornada)
            ->whereIn('origen', ['reparto_semanal', 'bonus_top3'])
            ->exists();
        if ($yaRepartida) {
            return response()->json(['message' => 'Las cartas de esta jornada ya se repartieron antes. Si necesitas corregir algo, usa el panel de admin para dar/retirar cartas sueltas.'], 409);
        }

        $topeMano = $liga->tope_mano_cartas ?? 8;
        $categorias = CategoriaCarta::where('activa', true)->get();

        if ($categorias->isEmpty()) {
            return response()->json(['message' => 'No hay ninguna categoría de carta activa todavía.'], 422);
        }

        $configuraciones = ConfiguracionCartasLiga::where('id_liga', $liga->id)->get()->keyBy('id_categoria');
        $rarezasPorCategoria = RarezaProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('id_categoria');
        $miembros = $liga->usuarios()->get(['users.id']);

        $cartasBaseCreadas = 0;
        $idsUsuariosSobreElTope = [];

        // Para avisar a cada jugador de lo que ha recibido: cuántas cartas, y si alguna es del bonus Top 3
        $recibidas = [];
        $posicionTop3 = [];

        foreach ($miembros as $miembro) {
            foreach ($categorias as $categoria) {
                $cantidad = $configuraciones->get($categoria->id)->cantidad_reparto_semanal ?? 1;
                $porcentajes = $this->porcentajesDeCategoria($rarezasPorCategoria, $categoria->id);

                for ($i = 0; $i < $cantidad; $i++) {
                    $tipoElegido = $this->sorteo->elegirCartaAlAzar($categoria->id, $porcentajes);

                    if ($tipoElegido) {
                        CartaUsuario::create([
                            'id_usuario' => $miembro->id,
                            'id_liga' => $liga->id,
                            'id_tipo_carta' => $tipoElegido->id,
                            'jornada_obtenida' => $jornada,
                            'obtenida_en' => now(),
                            'origen' => 'reparto_semanal',
                            'estado' => 'en_mano',
                        ]);
                        $cartasBaseCreadas++;
                        $recibidas[$miembro->id] = ($recibidas[$miembro->id] ?? 0) + 1;
                    }
                }
            }

            $manoFinal = CartaUsuario::where('id_liga', $liga->id)->where('id_usuario', $miembro->id)->where('estado', 'en_mano')->count();
            if ($manoFinal > $topeMano) {
                $idsUsuariosSobreElTope[] = $miembro->id;
            }
        }

        // --- Bonus del Top 3 de esta jornada concreta ---
        $top3 = EventoPuntos::where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->selectRaw('id_usuario, SUM(puntos) as puntos')
            ->groupBy('id_usuario')
            ->orderByDesc('puntos')
            ->limit(3)
            ->get()
            ->values();

        $bonusPorcentajesGuardados = BonusTop3ProbabilidadLiga::where('id_liga', $liga->id)->get()->groupBy('posicion');
        $cartasBonusCreadas = 0;

        foreach ($top3 as $indice => $fila) {
            if ($fila->puntos <= 0) {
                continue;
            }

            $posicion = $indice + 1;

            $filasPosicion = $bonusPorcentajesGuardados->get($posicion) ?? collect();
            $porcentajes = $filasPosicion->isNotEmpty()
                ? $filasPosicion->pluck('porcentaje', 'rareza')->toArray()
                : ProbabilidadesPorDefecto::TOP3[$posicion];

            $categoriaAlAzar = $categorias->random();
            $tipoElegido = $this->sorteo->elegirCartaAlAzar($categoriaAlAzar->id, $porcentajes);

            if ($tipoElegido) {
                CartaUsuario::create([
                    'id_usuario' => $fila->id_usuario,
                    'id_liga' => $liga->id,
                    'id_tipo_carta' => $tipoElegido->id,
                    'jornada_obtenida' => $jornada,
                    'obtenida_en' => now(),
                    'origen' => 'bonus_top3',
                    'estado' => 'en_mano',
                ]);
                $cartasBonusCreadas++;
                $recibidas[$fila->id_usuario] = ($recibidas[$fila->id_usuario] ?? 0) + 1;
                $posicionTop3[$fila->id_usuario] = $posicion;

                $manoFinal = CartaUsuario::where('id_liga', $liga->id)->where('id_usuario', $fila->id_usuario)->where('estado', 'en_mano')->count();
                if ($manoFinal > $topeMano && ! in_array($fila->id_usuario, $idsUsuariosSobreElTope)) {
                    $idsUsuariosSobreElTope[] = $fila->id_usuario;
                }
            }
        }

        $this->avisarReparto($recibidas, $posicionTop3, $idsUsuariosSobreElTope, $jornada);

        $mensajeAviso = count($idsUsuariosSobreElTope) > 0
            ? ' ⚠️ '.count($idsUsuariosSobreElTope).' usuario(s) han superado el tope de su mano — tendrán que descartar alguna carta ellos mismos desde Mis Cartas antes de poder jugar más.'
            : '';

        return response()->json([
            'message' => "Reparto completado: {$cartasBaseCreadas} carta(s) base + {$cartasBonusCreadas} carta(s) de bonus Top 3.{$mensajeAviso}",
        ]);
    }

    /**
     * Avisa por la campana a cada jugador de las cartas que ha recibido. Un fallo al
     * avisar NUNCA debe deshacer ni repetir el reparto (las cartas ya están creadas),
     * así que cada aviso va protegido por separado.
     */
    private function avisarReparto(array $recibidas, array $posicionTop3, array $idsSobreElTope, int $jornada): void
    {
        if (empty($recibidas)) {
            return;
        }

        User::whereIn('id', array_keys($recibidas))->get()->each(function (User $usuario) use ($recibidas, $posicionTop3, $idsSobreElTope, $jornada) {
            try {
                $usuario->notify(new CartasRepartidas(
                    cantidad: $recibidas[$usuario->id],
                    jornada: $jornada,
                    posicionTop3: $posicionTop3[$usuario->id] ?? null,
                    sobreElTope: in_array($usuario->id, $idsSobreElTope),
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function porcentajesDeCategoria(Collection $rarezasPorCategoria, int $idCategoria): array
    {
        $filas = $rarezasPorCategoria->get($idCategoria) ?? collect();

        if ($filas->isEmpty()) {
            return ProbabilidadesPorDefecto::RAREZA;
        }

        return $filas->pluck('porcentaje', 'rareza')->toArray();
    }
}