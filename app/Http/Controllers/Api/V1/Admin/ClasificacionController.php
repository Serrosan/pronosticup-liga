<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventoPuntos;
use App\Models\User;
use Illuminate\Http\Request;

class ClasificacionController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $eventos = EventoPuntos::where('id_liga', $liga->id)->get();
        $porUsuario = $eventos->groupBy('id_usuario');

        $filas = $porUsuario->map(function ($grupo, $idUsuario) {
            $usuario = User::find($idUsuario);

            return [
                'id_usuario' => (int) $idUsuario,
                'usuario' => $usuario->nombre_visible ?? $usuario->name,
                'avatar_url' => $usuario->avatar_url,
                'puntos_totales' => (int) $grupo->sum('puntos'),
                'aciertos' => $grupo->whereIn('tipo_evento', ['AciertoExacto', 'Acierto1x2'])->count(),
                'fallos' => $grupo->where('tipo_evento', 'Fallo')->count(),
                'exactos' => $grupo->where('tipo_evento', 'AciertoExacto')->count(),
            ];
        })->sortByDesc('puntos_totales')->values();

        $lider = $filas->first();

        $filasEnriquecidas = $filas->map(function ($fila) use ($lider) {
            $totalPronosticos = $fila['aciertos'] + $fila['fallos'];

            return array_merge($fila, [
                'diferencia_lider' => $lider['puntos_totales'] - $fila['puntos_totales'],
                'porcentaje_acierto' => $totalPronosticos > 0 ? round($fila['aciertos'] / $totalPronosticos * 100) : 0,
            ]);
        })->values();

        return response()->json(['data' => $filasEnriquecidas]);
    }
}