<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
}