<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use Illuminate\Http\Request;

class EquipoPartidosController extends Controller
{
    public function index(Request $request, Equipo $equipo)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where(function ($q) use ($equipo) {
                $q->where('id_equipo_local', $equipo->id)->orWhere('id_equipo_visitante', $equipo->id);
            })
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('jornada')
            ->get();

        return response()->json([
            'data' => [
                'equipo' => [
                    'id' => $equipo->id,
                    'nombre' => $equipo->nombre_corto ?? $equipo->nombre,
                    'escudo_url' => $equipo->escudo_url,
                    'camiseta_1' => $equipo->camiseta_1,
                    'camiseta_2' => $equipo->camiseta_2,
                    'camiseta_3' => $equipo->camiseta_3,
                    'camiseta_1_reverso' => $equipo->camiseta_1_reverso,
                    'camiseta_2_reverso' => $equipo->camiseta_2_reverso,
                    'camiseta_3_reverso' => $equipo->camiseta_3_reverso,
                ],
                'partidos' => $partidos->map(fn ($p) => [
                    'id' => $p->id,
                    'jornada' => $p->jornada,
                    'es_local' => $p->id_equipo_local === $equipo->id,
                    'rival' => $p->id_equipo_local === $equipo->id
                        ? ($p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre)
                        : ($p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre),
                    'escudo_rival' => $p->id_equipo_local === $equipo->id ? $p->equipoVisitante->escudo_url : $p->equipoLocal->escudo_url,
                    'horario_estimado' => $p->horario_estimado?->format('Y-m-d H:i'),
                    'estado' => $p->estado,
                    'goles_casa' => $p->goles_casa,
                    'goles_fuera' => $p->goles_fuera,
                ]),
            ],
        ]);
    }
}