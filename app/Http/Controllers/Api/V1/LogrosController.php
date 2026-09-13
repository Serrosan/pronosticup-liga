<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\EventoPartido;
use App\Models\EventoPuntos;
use App\Models\GoleadorJornada;
use App\Models\MensajeChat;
use App\Models\Pronostico;
use Illuminate\Http\Request;

class LogrosController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $userId = $request->user()->id;

        $eventos = EventoPuntos::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereNotNull('id_partido')
            ->with('partido')
            ->get()
            ->sortBy(fn ($e) => $e->partido?->horario_estimado)
            ->values();

        $tieneAlgunPronostico = Pronostico::where('id_liga', $liga->id)->where('id_usuario', $userId)->exists();

        $exactos = $eventos->where('tipo_evento', 'AciertoExacto');
        $diferencias = $eventos->where('tipo_evento', 'AciertoDiferencia');

        $dobleteExacto = $exactos->groupBy(fn ($e) => $e->jornada)->contains(fn ($grupo) => $grupo->count() >= 2);

        $modoGallina = $exactos->contains(fn ($e) => $e->partido && $e->partido->goles_casa === 0 && $e->partido->goles_fuera === 0);

        // --- Rachas históricas (la mejor racha que has tenido nunca, no solo la actual) ---
        $rachaActual = 0;
        $mejorRachaAciertos = 0;
        $rachaFallosActual = 0;
        $mejorRachaFallos = 0;

        foreach ($eventos as $evento) {
            if ($evento->tipo_evento === 'Fallo') {
                $rachaActual = 0;
                $rachaFallosActual++;
                $mejorRachaFallos = max($mejorRachaFallos, $rachaFallosActual);
            } else {
                $rachaFallosActual = 0;
                $rachaActual++;
                $mejorRachaAciertos = max($mejorRachaAciertos, $rachaActual);
            }
        }

        // --- Pleno de signos: en alguna jornada, acertaste el signo de TODOS los partidos ---
        $porJornada = $eventos->groupBy('jornada');
        $plenoSignos = false;

        foreach ($porJornada as $jornada => $grupo) {
            $totalPartidosJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->where('jornada', $jornada)
                ->count();

            $aciertosSigno = $grupo->whereIn('tipo_evento', ['Acierto1x2', 'AciertoDiferencia', 'AciertoExacto'])->count();

            if ($totalPartidosJornada > 0 && $aciertosSigno === $totalPartidosJornada) {
                $plenoSignos = true;
                break;
            }
        }

        // --- Jornada ganada: fuiste el máximo anotador de esa jornada en tu liga ---
        $puntosPorJornadaYUsuario = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('jornada, id_usuario, SUM(puntos) as puntos')
            ->groupBy('jornada', 'id_usuario')
            ->get()
            ->groupBy('jornada');

        $jornadaGanada = $puntosPorJornadaYUsuario->contains(function ($grupo) use ($userId) {
            $maximo = $grupo->max('puntos');
            $miPuntos = $grupo->firstWhere('id_usuario', $userId)?->puntos;
            return ! is_null($miPuntos) && $miPuntos == $maximo && $maximo > 0;
        });

        // --- Golazo de tu goleador: un jugador elegido por ti marcó 2+ goles en una jornada ---
        $seleccionesGoleadores = GoleadorJornada::where('id_liga', $liga->id)->where('id_usuario', $userId)->get();
        $golazoGoleador = false;

        foreach ($seleccionesGoleadores as $seleccion) {
            $idsPartidosJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->where('jornada', $seleccion->jornada)
                ->pluck('id');

            $golesDeEseJugador = EventoPartido::whereIn('id_partido', $idsPartidosJornada)
                ->where('tipo_evento', 'gol')
                ->where('id_jugador', $seleccion->id_jugador)
                ->count();

            if ($golesDeEseJugador >= 2) {
                $golazoGoleador = true;
                break;
            }
        }

        $mensajesEnviados = MensajeChat::where('id_liga', $liga->id)->where('id_usuario', $userId)->count();
        $tieneMensajeFijado = MensajeChat::where('id_liga', $liga->id)->where('id_usuario', $userId)->where('fijado', true)->exists();

        // --- Podio actual ---
        $clasificacion = EventoPuntos::where('id_liga', $liga->id)
            ->selectRaw('id_usuario, SUM(puntos) as total')
            ->groupBy('id_usuario')
            ->orderByDesc('total')
            ->pluck('id_usuario')
            ->values();

        $posicionActual = $clasificacion->search($userId);
        $enPodio = $posicionActual !== false && $posicionActual < 3;

        $jornadasParticipadas = Pronostico::where('id_liga', $liga->id)
            ->where('id_usuario', $userId)
            ->whereHas('partido')
            ->with('partido')
            ->get()
            ->pluck('partido.jornada')
            ->unique()
            ->count();

        $logros = [
            ['id' => 'bienvenido', 'icono' => '🎉', 'titulo' => 'Bienvenido', 'descripcion' => 'Haz tu primer pronóstico.', 'conseguido' => $tieneAlgunPronostico],
            ['id' => 'exacto', 'icono' => '💯', 'titulo' => 'Resultado exacto', 'descripcion' => 'Acierta un marcador exacto.', 'conseguido' => $exactos->count() >= 1],
            ['id' => 'doblete', 'icono' => '🎯', 'titulo' => 'Doblete', 'descripcion' => 'Acierta 2 resultados exactos en la misma jornada.', 'conseguido' => $dobleteExacto],
            ['id' => 'halcon', 'icono' => '🔮', 'titulo' => 'Ojo de halcón', 'descripcion' => 'Acumula 5 resultados exactos en la temporada.', 'conseguido' => $exactos->count() >= 5],
            ['id' => 'comodin', 'icono' => '🎪', 'titulo' => 'Comodín', 'descripcion' => 'Acumula 5 aciertos de diferencia en la temporada.', 'conseguido' => $diferencias->count() >= 5],
            ['id' => 'gallina', 'icono' => '🐔', 'titulo' => 'Modo gallina', 'descripcion' => 'Acierta exacto un 0-0.', 'conseguido' => $modoGallina],
            ['id' => 'racha', 'icono' => '🔥', 'titulo' => 'En racha', 'descripcion' => 'Llega a llevar 5 aciertos seguidos.', 'conseguido' => $mejorRachaAciertos >= 5],
            ['id' => 'racha_fria', 'icono' => '🧊', 'titulo' => 'Racha fría', 'descripcion' => 'Llega a llevar 3 fallos seguidos.', 'conseguido' => $mejorRachaFallos >= 3],
            ['id' => 'pleno', 'icono' => '🎯', 'titulo' => 'Pleno de signos', 'descripcion' => 'Acierta el signo de todos los partidos de una jornada.', 'conseguido' => $plenoSignos],
            ['id' => 'jornada_ganada', 'icono' => '🥇', 'titulo' => 'Jornada ganada', 'descripcion' => 'Sé el que más puntos saca en alguna jornada de tu liga.', 'conseguido' => $jornadaGanada],
            ['id' => 'golazo', 'icono' => '🥅', 'titulo' => 'Golazo de tu goleador', 'descripcion' => 'Un jugador que elegiste marca 2 o más goles en una jornada.', 'conseguido' => $golazoGoleador],
            ['id' => 'hablador', 'icono' => '🎙️', 'titulo' => 'Hablador', 'descripcion' => 'Envía 20 o más mensajes en el chat de esta liga.', 'conseguido' => $mensajesEnviados >= 20],
            ['id' => 'voz_oficial', 'icono' => '📌', 'titulo' => 'Voz oficial', 'descripcion' => 'Fija un mensaje en el chat (solo admins).', 'conseguido' => $tieneMensajeFijado],
            ['id' => 'podio', 'icono' => '🏅', 'titulo' => 'Podio actual', 'descripcion' => 'Está ahora mismo en el top 3 de tu liga.', 'conseguido' => $enPodio],
            ['id' => 'constante', 'icono' => '📈', 'titulo' => 'Constante', 'descripcion' => 'Participa en 5 o más jornadas distintas.', 'conseguido' => $jornadasParticipadas >= 5],
        ];

        return response()->json(['data' => $logros]);
    }
}
