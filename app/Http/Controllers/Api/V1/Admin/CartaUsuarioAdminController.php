<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\Liga;
use App\Models\User;
use App\Notifications\CartaRecibidaManual;
use Illuminate\Http\Request;

class CartaUsuarioAdminController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'id_liga' => ['required', 'exists:ligas,id'],
            'id_usuario' => ['required', 'exists:users,id'],
        ]);

        $cartas = CartaUsuario::where('id_liga', $validated['id_liga'])
            ->where('id_usuario', $validated['id_usuario'])
            ->whereIn('estado', ['en_mano', 'pendiente_resolucion'])
            ->with('tipoCarta.categoria')
            ->orderByDesc('obtenida_en')
            ->get();

        return response()->json(['data' => $cartas]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_liga' => ['required', 'exists:ligas,id'],
            'id_usuario' => ['required', 'exists:users,id'],
            'id_tipo_carta' => ['required', 'exists:tipos_carta,id'],
        ]);

        $liga = Liga::findOrFail($validated['id_liga']);

        $esMiembro = $liga->usuarios()->where('id_usuario', $validated['id_usuario'])->exists();
        if (! $esMiembro) {
            return response()->json(['message' => 'Ese usuario no pertenece a esa liga.'], 422);
        }

        $jornadaActual = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereIn('estado', ['Programado', 'Aplazado', 'En juego'])
            ->orderBy('jornada')
            ->value('jornada') ?? 1;

        $carta = CartaUsuario::create([
            'id_usuario' => $validated['id_usuario'],
            'id_liga' => $validated['id_liga'],
            'id_tipo_carta' => $validated['id_tipo_carta'],
            'jornada_obtenida' => $jornadaActual,
            'obtenida_en' => now(),
            'origen' => 'manual',
            'estado' => 'en_mano',
        ]);

        $carta->load('tipoCarta.categoria');

        $usuario = User::find($validated['id_usuario']);
        $usuario?->notify(new CartaRecibidaManual($carta->tipoCarta->nombre));

        return response()->json(['data' => $carta]);
    }

    public function destroy(CartaUsuario $cartaUsuario)
    {
        if (! in_array($cartaUsuario->estado, ['en_mano', 'pendiente_resolucion'])) {
            return response()->json(['message' => 'Esta carta ya no está activa (ya se jugó o se resolvió), no se puede retirar.'], 422);
        }

        $cartaUsuario->update(['estado' => 'retirada_manual']);

        return response()->json(['message' => 'Carta retirada.']);
    }
}
