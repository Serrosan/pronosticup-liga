<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use Illuminate\Http\Request;

class MisCartasController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['data' => ['activo' => false, 'cartas' => [], 'jugadas' => [], 'sin_abrir' => 0, 'tope_mano_cartas' => null]]);
        }

        // Solo mostramos en la mano las cartas ya "abiertas" — las que aún no se han
        // revelado no cuentan como visibles todavía, aunque ya sean tuyas de verdad.
        $motor = app(\App\Services\MotorEfectosCartas::class);

        $cartasEnMano = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNotNull('revelada_en')
            ->with('tipoCarta.categoria')
            ->orderByDesc('revelada_en')
            ->get()
            ->map(function ($c) use ($motor) {
                $datos = $c->toArray();
                $datos['requiere_partido'] = $motor->requiereEleccionDePartido($c->tipoCarta->codigo_efecto);
                return $datos;
            });

        $sinAbrir = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNull('revelada_en')
            ->count();

        $cartasJugadas = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('estado', ['jugada', 'pendiente_resolucion'])
            ->with(['tipoCarta.categoria', 'partido.equipoLocal', 'partido.equipoVisitante'])
            ->orderByDesc('jugada_en')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'tipo_carta' => $c->tipoCarta,
                'jugada_en' => $c->jugada_en?->toIso8601String(),
                'partido' => $c->partido ? [
                    'equipo_local' => $c->partido->equipoLocal->nombre_corto ?? $c->partido->equipoLocal->nombre,
                    'equipo_visitante' => $c->partido->equipoVisitante->nombre_corto ?? $c->partido->equipoVisitante->nombre,
                    'jornada' => $c->partido->jornada,
                ] : null,
            ]);

        return response()->json([
            'data' => [
                'activo' => true,
                'tope_mano_cartas' => $liga->tope_mano_cartas ?? 8,
                'cartas' => $cartasEnMano,
                'jugadas' => $cartasJugadas,
                'sin_abrir' => $sinAbrir,
            ],
        ]);
    }

    /**
     * "Abre" la carta sin revelar más antigua que tengas pendiente y devuelve sus datos
     * completos — el sorteo ya ocurrió al repartirla, esto solo la marca como vista.
     */
    public function abrirSiguiente(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $carta = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNull('revelada_en')
            ->with('tipoCarta.categoria')
            ->orderBy('obtenida_en')
            ->first();

        if (! $carta) {
            return response()->json(['message' => 'No tienes ninguna carta pendiente de abrir.'], 422);
        }

        $carta->update(['revelada_en' => now()]);

        return response()->json(['data' => $carta]);
    }

    public function proximaJornadaJugable(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $proximaJornadaNumero = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereIn('estado', ['Programado', 'En juego'])
            ->orderBy('horario_estimado')
            ->value('jornada');

        if (! $proximaJornadaNumero) {
            return response()->json(['data' => ['jornada' => null, 'partidos' => []]]);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $proximaJornadaNumero)
            ->where('estado', 'Programado')
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                'escudo_local' => $p->equipoLocal->escudo_url,
                'escudo_visitante' => $p->equipoVisitante->escudo_url,
                'horario_estimado' => $p->horario_estimado?->format('d/m H:i'),
            ]);

        return response()->json(['data' => ['jornada' => $proximaJornadaNumero, 'partidos' => $partidos]]);
    }

    public function descartar(Request $request, CartaUsuario $cartaUsuario)
    {
        if ($cartaUsuario->id_usuario !== $request->user()->id) {
            return response()->json(['message' => 'Esta carta no es tuya.'], 403);
        }

        if ($cartaUsuario->estado !== 'en_mano') {
            return response()->json(['message' => 'Esta carta ya no está en tu mano.'], 422);
        }

        $cartaUsuario->update(['estado' => 'descartada']);

        return response()->json(['message' => 'Carta descartada.']);
    }

    public function jugar(Request $request, CartaUsuario $cartaUsuario)
    {
        if ($cartaUsuario->id_usuario !== $request->user()->id) {
            return response()->json(['message' => 'Esta carta no es tuya.'], 403);
        }

        if ($cartaUsuario->estado !== 'en_mano') {
            return response()->json(['message' => 'Esta carta ya no está en tu mano.'], 422);
        }

        $liga = $request->user()->ligaActiva;
        $topeMano = $liga->tope_mano_cartas ?? 8;

        $manoActual = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->count();

        if ($manoActual > $topeMano) {
            return response()->json([
                'message' => "Tienes {$manoActual} cartas en mano y el límite es {$topeMano}. Descarta alguna antes de poder jugar otra.",
            ], 422);
        }

        $cartaUsuario->load('tipoCarta');
        $motor = app(\App\Services\MotorEfectosCartas::class);
        $requierePartido = $motor->requiereEleccionDePartido($cartaUsuario->tipoCarta->codigo_efecto);

        if (! $requierePartido) {
            // Cartas de Forma 3 (ej. Crack): se juegan "en genérico" para la
            // próxima jornada jugable, sin elegir ningún partido concreto.
            $proximaJornada = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->whereIn('estado', ['Programado', 'En juego'])
                ->orderBy('horario_estimado')
                ->value('jornada');

            if (! $proximaJornada) {
                return response()->json(['message' => 'No hay ninguna jornada disponible para jugar esta carta ahora mismo.'], 422);
            }

            $cartaUsuario->update([
                'estado' => 'jugada',
                'id_partido' => null,
                'jornada_efecto' => $proximaJornada,
                'jugada_en' => now(),
            ]);

            return response()->json(['message' => 'Carta jugada para la próxima jornada.']);
        }

        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
        ]);

        $partido = CalendarioPartido::findOrFail($validated['id_partido']);

        if ($partido->estado !== 'Programado') {
            return response()->json(['message' => 'Ese partido ya no admite jugar cartas sobre él.'], 422);
        }

        if (CalendarioPartido::jornadaBloqueada($partido->id_temporada, $partido->jornada)) {
            return response()->json(['message' => 'Esa jornada ya está bloqueada.'], 422);
        }

        $cartaUsuario->update([
            'estado' => 'jugada',
            'id_partido' => $partido->id,
            'jornada_efecto' => $partido->jornada,
            'jugada_en' => now(),
        ]);

        return response()->json(['message' => 'Carta jugada sobre ese partido.']);
    }
}