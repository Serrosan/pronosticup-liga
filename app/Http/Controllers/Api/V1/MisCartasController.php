<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CartaUsuario;
use Illuminate\Http\Request;
use App\Models\CalendarioPartido;

class MisCartasController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['data' => ['activo' => false, 'cartas' => [], 'tope_mano_cartas' => null]]);
        }

        $cartas = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('estado', ['en_mano', 'pendiente_resolucion'])
            ->with('tipoCarta.categoria')
            ->orderByDesc('obtenida_en')
            ->get();

        return response()->json([
            'data' => [
                'activo' => true,
                'tope_mano_cartas' => $liga->tope_mano_cartas ?? 8,
                'cartas' => $cartas,
            ],
        ]);
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