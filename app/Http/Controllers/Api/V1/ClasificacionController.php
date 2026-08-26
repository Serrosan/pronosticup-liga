<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\User;
use Illuminate\Http\Request;

class ClasificacionController extends Controller
{
    public function index(Request $request, Liga $liga)
    {
        $esMiembro = $liga->usuarios()->where('id_usuario', $request->user()->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esta liga.'], 403);
        }

        $clasificacion = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('id_usuario, SUM(puntos) as puntos_totales, COUNT(*) as pronosticos_evaluados')
            ->groupBy('id_usuario')
            ->orderByDesc('puntos_totales')
            ->get()
            ->map(function ($fila) {
                $usuario = User::find($fila->id_usuario);
                return [
                    'usuario' => $usuario->nombre_visible ?? $usuario->name,
                    'puntos_totales' => (int) $fila->puntos_totales,
                    'pronosticos_evaluados' => $fila->pronosticos_evaluados,
                ];
            })
            ->values();

        return response()->json(['data' => $clasificacion]);
    }
}