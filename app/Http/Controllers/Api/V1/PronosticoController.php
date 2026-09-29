<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PronosticoResource;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\ConfiguracionPuntos;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\Pronostico;
use Illuminate\Http\Request;

class PronosticoController extends Controller
{
    public function store(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
            'goles_local_predicho' => ['required', 'integer', 'min:0'],
            'goles_visitante_predicho' => ['required', 'integer', 'min:0'],
        ]);

        $partido = CalendarioPartido::findOrFail($validated['id_partido']);

        if (CalendarioPartido::jornadaBloqueada($liga->id_temporada, $partido->jornada)) {
            return response()->json(['message' => 'Esta jornada ya no admite pronósticos: ya ha empezado al menos un partido.'], 422);
        }

        $resultado1x2 = $this->calcularResultado($validated['goles_local_predicho'], $validated['goles_visitante_predicho']);

        // Prevalidación de Faltas tipo "requisito de pronóstico" (Todo queda en
        // casa / Visita Obligada): si guardar esto deja matemáticamente
        // imposible cumplir el requisito, se bloquea ANTES de tocar la BD.
        $totalPartidosJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $partido->jornada)
            ->count();

        $otrosPronosticosDeEstaJornada = Pronostico::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->where('id_partido', '!=', $partido->id)
            ->whereHas('partido', fn ($q) => $q->where('jornada', $partido->jornada))
            ->pluck('resultado_1x2');

        $tiposConEsteIncluido = $otrosPronosticosDeEstaJornada->push($resultado1x2);

        $problemaFalta = app(\App\Services\MotorFaltas::class)->comprobarRequisitoPronostico(
            $liga->id,
            $request->user()->id,
            $partido->jornada,
            $totalPartidosJornada,
            $tiposConEsteIncluido->count(),
            $tiposConEsteIncluido
        );

        if ($problemaFalta) {
            return response()->json(['message' => $problemaFalta], 422);
        }

        $pronostico = Pronostico::updateOrCreate(
            [
                'id_usuario' => $request->user()->id,
                'id_liga' => $liga->id,
                'id_partido' => $validated['id_partido'],
            ],
            [
                'resultado_1x2' => $resultado1x2,
                'goles_local_predicho' => $validated['goles_local_predicho'],
                'goles_visitante_predicho' => $validated['goles_visitante_predicho'],
                'enviado_en' => now(),
            ]
        );

        return new PronosticoResource($pronostico);
    }

    public function misPronosticos(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $pronosticos = Pronostico::where('id_usuario', $request->user()->id)
            ->where('id_liga', $liga->id)
            ->whereHas('partido', fn ($q) => $q->where('jornada', $jornada))
            ->get();

        return PronosticoResource::collection($pronosticos);
    }

    /**
     * Pronósticos de TODOS los demás miembros de la liga en una jornada — solo
     * visible una vez esa jornada está bloqueada (empezó al menos un partido).
     * Fusionado aquí desde OtrosPronosticosController (era single-action, sin
     * justificación propia para vivir fuera de PronosticoController).
     */
    public function deOtros(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if (! CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada)) {
            return response()->json(['message' => 'Los pronósticos de esta jornada aún no se pueden ver.'], 403);
        }

        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->whereIn('id_partido', $idsPartidos)
            ->where('id_usuario', '!=', $request->user()->id)
            ->with('usuario')
            ->get()
            ->groupBy('id_partido')
            ->map(fn ($grupo) => $grupo->map(fn ($p) => [
                'usuario' => $p->usuario->nombre_visible ?? $p->usuario->name,
                'avatar_url' => $p->usuario->avatar_url ? url($p->usuario->avatar_url) : null,
                'pronostico' => "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}",
            ]));

        return response()->json(['data' => $pronosticos]);
    }

    public function progresoLiga(Request $request, int $jornada)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $miembros = $liga->usuarios()->get(['users.id', 'users.name', 'users.nombre_visible']);
        $totalPartidos = $idsPartidos->count();

        if ($totalPartidos === 0) {
            return response()->json(['data' => ['completados' => 0, 'total_miembros' => $miembros->count(), 'pendientes' => []]]);
        }

        $conteoPorUsuario = Pronostico::where('id_liga', $liga->id)
            ->whereIn('id_partido', $idsPartidos)
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->pluck('total', 'id_usuario');

        $completados = 0;
        $pendientes = [];

        foreach ($miembros as $miembro) {
            $hechos = $conteoPorUsuario->get($miembro->id, 0);

            if ($hechos >= $totalPartidos) {
                $completados++;
            } else {
                $pendientes[] = [
                    'nombre' => $miembro->nombre_visible ?? $miembro->name,
                    'faltan' => $totalPartidos - $hechos,
                ];
            }
        }

        return response()->json([
            'data' => [
                'completados' => $completados,
                'total_miembros' => $miembros->count(),
                'pendientes' => $pendientes,
            ],
        ]);
    }

    public function resumenRendimiento(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $porJornada = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->selectRaw('jornada, SUM(puntos) as puntos')
            ->groupBy('jornada')
            ->get();

        if ($porJornada->isEmpty()) {
            return response()->json(['data' => ['mejor_jornada' => null, 'mejor_puntos' => null, 'media_puntos' => null]]);
        }

        $mejor = $porJornada->sortByDesc('puntos')->first();
        $media = round($porJornada->avg('puntos'), 1);

        return response()->json([
            'data' => [
                'mejor_jornada' => $mejor->jornada,
                'mejor_puntos' => (int) $mejor->puntos,
                'media_puntos' => $media,
            ],
        ]);
    }

    public function todos(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $userId = $request->user()->id;
        $config = ConfiguracionPuntos::paraLiga($liga->id);

        $pronosticos = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->with(['partido.equipoLocal', 'partido.equipoVisitante'])
            ->get();

        $idsPartidos = $pronosticos->pluck('id_partido');

        // Un partido puede tener VARIOS eventos (el del resultado y, si hay carta, el de la
        // carta). Antes se indexaban por partido y el último pisaba al anterior.
        $tiposBase = ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2', 'Fallo'];

        $eventosPorPartido = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('id_partido', $idsPartidos)
            ->get()
            ->groupBy('id_partido');

        $cartasPorPartido = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('id_partido', $idsPartidos)
            ->whereIn('estado', ['resuelta_cumplida', 'resuelta_no_cumplida'])
            ->with('tipoCarta')
            ->get()
            ->groupBy('id_partido');

        $bonusPlenoPorJornada = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->where('tipo_evento', 'BonusPleno')
            ->get()
            ->groupBy('jornada')
            ->map(fn ($grupo) => (int) $grupo->sum('puntos'));

        // Bonus de cartas que son de la jornada entera, no de un partido (Crack, Doble Filo...)
        $bonusCartasPorJornada = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereIn('tipo_evento', ['CartaBonoJornada', 'CartaDoblePartido'])
            ->get()
            ->groupBy('jornada')
            ->map(fn ($grupo) => (int) $grupo->sum('puntos'));

        // En qué jornadas un Amuleto ayudó de verdad — para poder explicar el bonus de pleno
        $jornadasConAmuletoActivo = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->where('estado', 'resuelta_cumplida')
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', ['JUG-PCOM-AMULETO', 'JUG-RAR-AMULETO', 'JUG-LEG-AMULETO']))
            ->pluck('jornada_efecto')
            ->flip();

        // Puntos de cartas ligadas a un partido concreto (Chute Extra, Ojo de Halcón, Doblete,
        // Pleno Garantizado, Palomitas, Amigo del Árbitro, las de minuto...) — ya vienen sumados
        // DENTRO de 'puntos' de cada partido, así que para un desglose sin duplicar hay que
        // restarlos de "pronósticos" en vez de sumarlos aparte.
        $puntosCartasPorPartidoTotal = (int) CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->where('estado', 'resuelta_cumplida')
            ->whereNotNull('id_partido')
            ->sum('puntos_generados');

        $filasPorPartido = $pronosticos->map(function ($p) use ($eventosPorPartido, $cartasPorPartido, $tiposBase) {
            $eventos = $eventosPorPartido->get($p->id_partido, collect());
            $base = $eventos->first(fn ($e) => in_array($e->tipo_evento, $tiposBase, true));
            $puntosCartaEvento = (int) $eventos->where('tipo_evento', 'CartaEventoPartido')->sum('puntos');

            return [
                'id' => $p->id,
                'id_partido' => $p->id_partido,
                'jornada' => $p->partido->jornada,
                'equipo_local' => $p->partido->equipoLocal->nombre_corto ?? $p->partido->equipoLocal->nombre,
                'equipo_visitante' => $p->partido->equipoVisitante->nombre_corto ?? $p->partido->equipoVisitante->nombre,
                'escudo_local' => $p->partido->equipoLocal->escudo_url,
                'escudo_visitante' => $p->partido->equipoVisitante->escudo_url,
                'horario_estimado' => $p->partido->horario_estimado?->format('d/m/Y H:i'),
                'estado_partido' => $p->partido->estado,
                'goles_casa' => $p->partido->goles_casa,
                'goles_fuera' => $p->partido->goles_fuera,
                'mi_pronostico' => "{$p->goles_local_predicho}-{$p->goles_visitante_predicho}",
                'resultado_1x2' => $p->resultado_1x2,
                // Puntos del partido = resultado (ya con las cartas de ajuste) + carta de evento
                'puntos' => $base ? (int) $base->puntos + $puntosCartaEvento : null,
                'tipo_evento' => $base?->tipo_evento,
                'nota_carta' => $base?->nota_carta,
                'cartas' => $cartasPorPartido->get($p->id_partido, collect())->map(fn ($c) => [
                    'nombre' => $c->tipoCarta->nombre,
                    'puntos' => (int) $c->puntos_generados,
                    'cumplida' => $c->estado === 'resuelta_cumplida',
                ])->values(),
            ];
        });

        $numerosJornada = $filasPorPartido->pluck('jornada')->unique()->sortDesc()->values();

        $jornadas = $numerosJornada->map(function ($jornada) use ($liga, $userId, $filasPorPartido, $bonusPlenoPorJornada, $bonusCartasPorJornada, $jornadasConAmuletoActivo, $config) {
            $partidosDeEstaJornada = $filasPorPartido->where('jornada', $jornada)->sortBy('horario_estimado')->values();

            $bloqueada = CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada);

            $idsPartidosJornada = $partidosDeEstaJornada->pluck('id_partido');

            $seleccionGoleadores = GoleadorJornada::where('id_liga', $liga->id)
                ->where('id_usuario', $userId)
                ->where('jornada', $jornada)
                ->with('jugador')
                ->get();

            $golesRealesPorJugador = EventoPartido::whereIn('id_partido', $idsPartidosJornada)
                ->where('tipo_evento', 'gol')
                ->get()
                ->countBy('id_jugador');

            $goleadores = $seleccionGoleadores->map(function ($seleccion) use ($golesRealesPorJugador, $config) {
                $goles = $golesRealesPorJugador->get($seleccion->id_jugador, 0);

                return [
                    'id' => $seleccion->id_jugador,
                    'nombre' => $seleccion->jugador->nombre_camiseta ?? trim("{$seleccion->jugador->nombre} {$seleccion->jugador->apellidos}"),
                    'foto_url' => $seleccion->jugador->foto_url,
                    'goles' => $goles,
                    'puntos' => $goles * $config->puntos_gol_goleador,
                ];
            });

            $puntosPartidos = (int) $partidosDeEstaJornada->sum('puntos');
            $puntosBonus = $bonusPlenoPorJornada->get($jornada, 0);
            $puntosBonusCartas = $bonusCartasPorJornada->get($jornada, 0);
            $puntosGoleadores = (int) $goleadores->sum('puntos');

            return [
                'jornada' => $jornada,
                'bloqueada' => $bloqueada,
                'puntos_totales_jornada' => $puntosPartidos + $puntosBonus + $puntosBonusCartas + $puntosGoleadores,
                'bonus_pleno' => $puntosBonus,
                // Si algún Amuleto de esa jornada protegió un fallo, el bonus de pleno puede
                // deberse en parte a él — se lo decimos al jugador para que no parezca un error.
                'bonus_pleno_con_amuleto' => $puntosBonus > 0 && $jornadasConAmuletoActivo->has($jornada),
                'bonus_cartas' => $puntosBonusCartas,
                'partidos' => $partidosDeEstaJornada,
                'goleadores' => $goleadores,
            ];
        });

        $puntosGoleadoresTotal = (int) $jornadas->sum(fn ($j) => collect($j['goleadores'])->sum('puntos'));
        $puntosBonusCartasTotal = (int) $bonusCartasPorJornada->sum();
        $puntosBonusPlenoTotal = (int) $bonusPlenoPorJornada->sum();

        return response()->json([
            'data' => [
                'stats' => [
                    'total' => $filasPorPartido->count(),
                    'puntos_totales' => (int) $filasPorPartido->sum('puntos') + $puntosBonusPlenoTotal + $puntosBonusCartasTotal + $puntosGoleadoresTotal,
                    'aciertos' => $filasPorPartido->whereIn('tipo_evento', ['AciertoExacto', 'AciertoDiferencia', 'Acierto1x2'])->count(),
                    'exactos' => $filasPorPartido->where('tipo_evento', 'AciertoExacto')->count(),
                ],
                // Desglose global, pensado para que "pronósticos + bono de pleno + cartas +
                // goleadores" sume EXACTAMENTE el total — "pronósticos" resta lo que ya
                // aportaron las cartas, para no contarlo dos veces.
                'desglose' => [
                    'pronosticos' => (int) $filasPorPartido->sum('puntos') - $puntosCartasPorPartidoTotal,
                    'bonus_pleno' => $puntosBonusPlenoTotal,
                    'cartas' => $puntosCartasPorPartidoTotal + $puntosBonusCartasTotal,
                    'goleadores' => $puntosGoleadoresTotal,
                ],
                'jornadas' => $jornadas,
            ],
        ]);
    }

    /**
     * Para el badge de "Pronósticos" en la barra: cuántos partidos de la próxima
     * jornada aún no has pronosticado. Ligero a propósito — nada de cartas, puntos
     * ni historial, solo lo justo para pintar un número. Si la jornada ya empezó,
     * no tiene sentido avisar de nada (ya no se puede actuar), así que sale 0.
     */
    public function pendientesCuenta(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['data' => ['jornada' => null, 'pendientes' => 0]]);
        }

        $proximoPartido = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('estado', 'Programado')
            ->orderBy('horario_estimado')
            ->first();

        if (! $proximoPartido) {
            return response()->json(['data' => ['jornada' => null, 'pendientes' => 0]]);
        }

        $jornada = $proximoPartido->jornada;

        if (CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada)) {
            return response()->json(['data' => ['jornada' => $jornada, 'pendientes' => 0]]);
        }

        $idsPartidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $yaPronosticados = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('id_partido', $idsPartidos)
            ->count();

        return response()->json(['data' => [
            'jornada' => $jornada,
            'pendientes' => max(0, $idsPartidos->count() - $yaPronosticados),
        ]]);
    }

    private function calcularResultado(int $golesLocal, int $golesVisitante): string
    {
        if ($golesLocal > $golesVisitante) return 'Local';
        if ($golesLocal < $golesVisitante) return 'Visitante';
        return 'Empate';
    }
}