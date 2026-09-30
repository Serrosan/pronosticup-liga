<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CartaUsuario;
use App\Models\User;
use Illuminate\Http\Request;

class EstadisticasCartasController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['message' => 'Esta liga no tiene el modo Cartas activado.'], 422);
        }

        $base = CartaUsuario::where('id_liga', $liga->id);

        // --- Quién ha sacado más Legendarias (cuenta lo obtenido, jugado o no) ---
        $masLegendarias = (clone $base)
            ->whereHas('tipoCarta', fn ($q) => $q->where('rareza', 'Legendaria'))
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->first();
        $usuarioMasLegendarias = $masLegendarias ? User::find($masLegendarias->id_usuario) : null;

        // --- La carta más jugada de la liga (de verdad jugada, no solo obtenida) ---
        $cartaMasJugada = (clone $base)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->selectRaw('id_tipo_carta, COUNT(*) as total')
            ->groupBy('id_tipo_carta')
            ->orderByDesc('total')
            ->with('tipoCarta.categoria')
            ->first();

        // --- Quién ha ganado más puntos gracias a las cartas ---
        $reyDePuntos = (clone $base)
            ->where('estado', 'resuelta_cumplida')
            ->selectRaw('id_usuario, SUM(puntos_generados) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->having('total', '>', 0)
            ->first();
        $usuarioReyDePuntos = $reyDePuntos ? User::find($reyDePuntos->id_usuario) : null;

        // --- El más agresivo: quién ha jugado más Faltas contra otros (nunca contra sí mismo) ---
        $masAgresivo = (clone $base)
            ->whereNotNull('id_usuario_objetivo')
            ->whereColumn('id_usuario', '!=', 'id_usuario_objetivo')
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->first();
        $usuarioMasAgresivo = $masAgresivo ? User::find($masAgresivo->id_usuario) : null;

        // --- El más atacado: quién ha recibido más Faltas ---
        $masAtacado = (clone $base)
            ->whereNotNull('id_usuario_objetivo')
            ->whereColumn('id_usuario', '!=', 'id_usuario_objetivo')
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->selectRaw('id_usuario_objetivo, COUNT(*) as total')
            ->groupBy('id_usuario_objetivo')
            ->orderByDesc('total')
            ->first();
        $usuarioMasAtacado = $masAtacado ? User::find($masAtacado->id_usuario_objetivo) : null;

        // --- Cuántas veces un Escudo bloqueó un ataque de verdad, en toda la liga ---
        $escudosExitosos = (clone $base)
            ->whereHas('tipoCarta', fn ($q) => $q->where('codigo_efecto', 'FAL-PCOM-ESCUDO'))
            ->where('estado', 'resuelta_cumplida')
            ->count();

        // --- Cuántos Amuletos protegieron un fallo con éxito, en toda la liga ---
        $amuletosExitosos = (clone $base)
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', ['JUG-PCOM-AMULETO', 'JUG-RAR-AMULETO', 'JUG-LEG-AMULETO']))
            ->where('estado', 'resuelta_cumplida')
            ->count();

        // --- Sobres abiertos en total, en toda la liga ---
        $sobresAbiertos = (clone $base)->whereNotNull('revelada_en')->count();

        return response()->json([
            'data' => [
                'mas_legendarias' => $usuarioMasLegendarias ? [
                    'nombre' => $usuarioMasLegendarias->nombre_visible ?? $usuarioMasLegendarias->name,
                    'avatar_url' => $usuarioMasLegendarias->avatar_url ? url($usuarioMasLegendarias->avatar_url) : null,
                    'total' => (int) $masLegendarias->total,
                ] : null,
                'carta_mas_jugada' => $cartaMasJugada && $cartaMasJugada->tipoCarta ? [
                    'nombre' => $cartaMasJugada->tipoCarta->nombre,
                    'rareza' => $cartaMasJugada->tipoCarta->rareza,
                    'categoria' => $cartaMasJugada->tipoCarta->categoria->nombre ?? null,
                    'imagen_url' => $cartaMasJugada->tipoCarta->imagen_url,
                    'total' => (int) $cartaMasJugada->total,
                ] : null,
                'rey_de_puntos' => $usuarioReyDePuntos ? [
                    'nombre' => $usuarioReyDePuntos->nombre_visible ?? $usuarioReyDePuntos->name,
                    'avatar_url' => $usuarioReyDePuntos->avatar_url ? url($usuarioReyDePuntos->avatar_url) : null,
                    'total' => (int) $reyDePuntos->total,
                ] : null,
                'mas_agresivo' => $usuarioMasAgresivo ? [
                    'nombre' => $usuarioMasAgresivo->nombre_visible ?? $usuarioMasAgresivo->name,
                    'avatar_url' => $usuarioMasAgresivo->avatar_url ? url($usuarioMasAgresivo->avatar_url) : null,
                    'total' => (int) $masAgresivo->total,
                ] : null,
                'mas_atacado' => $usuarioMasAtacado ? [
                    'nombre' => $usuarioMasAtacado->nombre_visible ?? $usuarioMasAtacado->name,
                    'avatar_url' => $usuarioMasAtacado->avatar_url ? url($usuarioMasAtacado->avatar_url) : null,
                    'total' => (int) $masAtacado->total,
                ] : null,
                'escudos_exitosos' => $escudosExitosos,
                'amuletos_exitosos' => $amuletosExitosos,
                'sobres_abiertos' => $sobresAbiertos,
            ],
        ]);
    }
}