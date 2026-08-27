<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventoPuntos;
use App\Models\Pronostico;
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
                'id_usuario' => $idUsuario,
                'usuario' => $usuario->nombre_visible ?? $usuario->name,
                'avatar_url' => $usuario->avatar_url ? url($usuario->avatar_url) : null,
                'puntos_totales' => (int) $grupo->sum('puntos'),
                'aciertos' => $grupo->whereIn('tipo_evento', ['AciertoExacto', 'Acierto1x2'])->count(),
                'fallos' => $grupo->where('tipo_evento', 'Fallo')->count(),
                'exactos' => $grupo->where('tipo_evento', 'AciertoExacto')->count(),
            ];
        })->sortByDesc('puntos_totales')->values();

        return response()->json(['data' => $filas]);
    }

    public function detalle(Request $request, User $usuario)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $esMiembro = $liga->usuarios()->where('id_usuario', $usuario->id)->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'Ese usuario no pertenece a tu liga.'], 403);
        }

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->with(['partido.equipoLocal', 'partido.equipoVisitante'])
            ->get();

        $idsPartidos = $pronosticos->pluck('id_partido');

        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $usuario->id)
            ->whereIn('id_partido', $idsPartidos)
            ->get()
            ->keyBy('id_partido');

        $filas = $pronosticos->map(function ($p) use ($eventos) {
            $evento = $eventos->get($p->id_partido);

            return [
                'id' => $p->id,
                'jornada' => $p->partido->jornada,
                'equipo_local' => $p->partido->equipoLocal->nombre_corto ?? $p->partido->equipoLocal->nombre,
                'equipo_visitante' => $p->partido->equipoVisitante->nombre_corto ?? $p->partido->equipoVisitante->nombre,
                'escudo_local' => $p->partido->equipoLocal->escudo_url,
                'escudo_visitante' => $p->partido->equipoVisitante->escudo_url,
                'estado_partido' => $p->partido->estado,
                'goles_casa' => $p->partido->goles_casa,
                'goles_fuera' => $p->partido->goles_fuera,
                'mi_pronostico' => "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}",
                'resultado_1x2' => $p->resultado_1x2,
                'puntos' => $evento?->puntos,
                'tipo_evento' => $evento?->tipo_evento,
            ];
        })->sortByDesc('jornada')->values();

        return response()->json([
            'data' => [
                'usuario' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre_visible ?? $usuario->name,
                    'avatar_url' => $usuario->avatar_url ? url($usuario->avatar_url) : null,
                ],
                'stats' => [
                    'total' => $filas->count(),
                    'puntos_totales' => (int) $filas->sum('puntos'),
                    'aciertos' => $filas->whereIn('tipo_evento', ['AciertoExacto', 'Acierto1x2'])->count(),
                    'exactos' => $filas->where('tipo_evento', 'AciertoExacto')->count(),
                ],
                'pronosticos' => $filas,
            ],
        ]);
    }
}