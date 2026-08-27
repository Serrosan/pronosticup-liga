<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\EventoPuntos;
use App\Models\Pronostico;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $userId = $request->user()->id;

        $clasificacion = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('id_usuario, SUM(puntos) as puntos_totales')
            ->groupBy('id_usuario')
            ->orderByDesc('puntos_totales')
            ->get();

        $miFila = $clasificacion->firstWhere('id_usuario', $userId);
        $posicion = $clasificacion->search(fn ($fila) => $fila->id_usuario === $userId);

        // --- La jornada más próxima con partidos sin jugar ---
        $proximaJornadaNumero = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereIn('estado', ['Programado', 'Aplazado'])
            ->orderBy('jornada')
            ->value('jornada');

        $partidosProximaJornada = collect();
        if ($proximaJornadaNumero) {
            $partidosProximaJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->where('jornada', $proximaJornadaNumero)
                ->with(['equipoLocal', 'equipoVisitante'])
                ->orderBy('horario_estimado')
                ->get();
        }

        // --- Avisos: partidos aplazados en la jornada actual o siguiente ---
        $avisos = $partidosProximaJornada
            ->where('estado', 'Aplazado')
            ->map(fn ($p) => [
                'tipo' => 'aplazado',
                'mensaje' => "{$p->equipoLocal->nombre} vs {$p->equipoVisitante->nombre} ha sido aplazado.",
            ])
            ->values();

        // --- Última jornada completa ya jugada ---
        $ultimaJornadaJugada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('estado', 'Jugado')
            ->orderByDesc('jornada')
            ->value('jornada');

        $ultimos = collect();
        if ($ultimaJornadaJugada) {
            $ultimos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->where('jornada', $ultimaJornadaJugada)
                ->where('estado', 'Jugado')
                ->with(['equipoLocal', 'equipoVisitante'])
                ->orderBy('horario_estimado')
                ->get();
        }

        $idsUltimos = $ultimos->pluck('id');

        $misPronosticos = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('id_partido', $idsUltimos)
            ->get()
            ->keyBy('id_partido');

        $misEventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('id_partido', $idsUltimos)
            ->get()
            ->keyBy('id_partido');

        // --- Evolución de puntos por jornada ---
        $eventosPorJornada = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('jornada, id_usuario, SUM(puntos) as puntos')
            ->groupBy('jornada', 'id_usuario')
            ->get();

        $jornadasConDatos = $eventosPorJornada->pluck('jornada')->unique()->sort()->values();
        $usuariosLiga = $liga->usuarios()->get(['users.id', 'users.name', 'users.nombre_visible']);

        $series = $usuariosLiga->map(function ($usuario) use ($eventosPorJornada, $jornadasConDatos) {
            $acumulado = 0;
            $datos = $jornadasConDatos->map(function ($jornada) use ($eventosPorJornada, $usuario, &$acumulado) {
                $fila = $eventosPorJornada->first(fn ($e) => $e->jornada == $jornada && $e->id_usuario == $usuario->id);
                $acumulado += $fila->puntos ?? 0;
                return $acumulado;
            });

            return [
                'usuario' => $usuario->nombre_visible ?? $usuario->name,
                'datos' => $datos->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'liga_nombre' => $liga->nombre,
                'puntos_totales' => (int) ($miFila->puntos_totales ?? 0),
                'posicion' => $posicion === false ? null : $posicion + 1,
                'total_participantes' => $clasificacion->count(),

                'avisos' => $avisos,

                'proxima_jornada' => [
                    'numero' => $proximaJornadaNumero,
                    'partidos' => $partidosProximaJornada->map(fn ($p) => [
                        'id' => $p->id,
                        'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                        'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                        'escudo_local' => $p->equipoLocal->escudo_url,
                        'escudo_visitante' => $p->equipoVisitante->escudo_url,
                        'horario_estimado' => $p->horario_estimado?->format('Y-m-d H:i'),
                        'estado' => $p->estado,
                    ]),
                ],

                'ultima_jornada_jugada' => $ultimaJornadaJugada,
                'ultimos_resultados' => $ultimos->map(function ($p) use ($misPronosticos, $misEventos) {
                    $pronostico = $misPronosticos->get($p->id);
                    $evento = $misEventos->get($p->id);

                    return [
                        'id' => $p->id,
                        'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                        'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                        'escudo_local' => $p->equipoLocal->escudo_url,
                        'escudo_visitante' => $p->equipoVisitante->escudo_url,
                        'goles_casa' => $p->goles_casa,
                        'goles_fuera' => $p->goles_fuera,
                        'mi_pronostico' => $pronostico
                            ? "{$pronostico->goles_local_predicho}-{$pronostico->goles_visitante_predicho}"
                            : null,
                        'puntos' => $evento?->puntos ?? 0,
                        'acerte' => $evento ? $evento->puntos > 0 : null,
                    ];
                }),

                'evolucion' => [
                    'jornadas' => $jornadasConDatos,
                    'series' => $series,
                ],
            ],
        ]);
    }
}