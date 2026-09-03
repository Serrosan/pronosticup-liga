<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use App\Models\Estadio;
use App\Models\Jugador;
use Illuminate\Http\Request;

class EquipoPartidosController extends Controller
{
    public function index(Request $request, Equipo $equipo)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $equipo->load(['estadio', 'entrenadorActual']);

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where(function ($q) use ($equipo) {
                $q->where('id_equipo_local', $equipo->id)->orWhere('id_equipo_visitante', $equipo->id);
            })
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('jornada')
            ->get();

        $capacidadMaxima = Estadio::max('capacidad');

        $plantilla = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $equipo->id)->whereNull('fecha_salida'))
            ->whereNull('dado_de_baja_en')
            ->with(['plantillasTemporada' => fn ($q) => $q->where('id_equipo', $equipo->id)->whereNull('fecha_salida')])
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'nombre' => $j->nombre,
                'apellidos' => $j->apellidos,
                'nombre_camiseta' => $j->nombre_camiseta,
                'posicion' => $j->posicion,
                'nacionalidad' => $j->nacionalidad,
                'foto_url' => $j->foto_url,
                'fecha_nacimiento' => $j->fecha_nacimiento?->format('Y-m-d'),
                'pie' => $j->pie,
                'dorsal' => $j->plantillasTemporada->first()?->dorsal,
            ])
            ->sortBy(fn ($j) => $j['dorsal'] ?? 99)
            ->values();

        $edades = $plantilla->map(fn ($j) => $j['fecha_nacimiento'] ? now()->diffInYears($j['fecha_nacimiento']) : null)->filter();

        return response()->json([
            'data' => [
                'equipo' => [
                    'id' => $equipo->id,
                    'nombre' => $equipo->nombre,
                    'nombre_corto' => $equipo->nombre_corto ?? $equipo->nombre,
                    'apodo' => $equipo->apodo,
                    'siglas' => $equipo->siglas,
                    'ciudad' => $equipo->ciudad,
                    'año_fundacion' => $equipo->año_fundacion,
                    'escudo_url' => $equipo->escudo_url,
                    'color_primario' => $equipo->color_primario,
                    'color_secundario' => $equipo->color_secundario,
                    'num_socios' => $equipo->num_socios,
                    'num_abonados' => $equipo->num_abonados,
                    'camiseta_1' => $equipo->camiseta_1,
                    'camiseta_2' => $equipo->camiseta_2,
                    'camiseta_3' => $equipo->camiseta_3,
                    'camiseta_1_reverso' => $equipo->camiseta_1_reverso,
                    'camiseta_2_reverso' => $equipo->camiseta_2_reverso,
                    'camiseta_3_reverso' => $equipo->camiseta_3_reverso,
                    'estadio' => $equipo->estadio ? [
                        'nombre' => $equipo->estadio->nombre,
                        'ciudad' => $equipo->estadio->ciudad,
                        'capacidad' => $equipo->estadio->capacidad,
                        'tamanio_campo' => $equipo->estadio->tamanio_campo,
                        'anio_construccion' => $equipo->estadio->anio_construccion,
                        'anio_ult_remodelacion' => $equipo->estadio->anio_ult_remodelacion,
                    ] : null,
                    'capacidad_maxima_laliga' => $capacidadMaxima,
                    'entrenador' => $equipo->entrenadorActual ? [
                        'nombre' => $equipo->entrenadorActual->nombre,
                        'nacionalidad' => $equipo->entrenadorActual->nacionalidad,
                        'foto_url' => $equipo->entrenadorActual->foto_url,
                    ] : null,
                ],
                'plantilla' => $plantilla,
                'plantilla_stats' => [
                    'total' => $plantilla->count(),
                    'edad_media' => $edades->count() > 0 ? round($edades->avg(), 1) : null,
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