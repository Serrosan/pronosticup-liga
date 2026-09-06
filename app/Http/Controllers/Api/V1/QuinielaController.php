<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\PrediccionQuiniela;
use App\Models\QuinielaPosiciones;
use Illuminate\Http\Request;

class QuinielaController extends Controller
{
    public function show(Request $request, string $tipo)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $quiniela = QuinielaPosiciones::where('id_liga', $liga->id)->where('tipo', $tipo)->first();

        if (! $quiniela) {
            return response()->json(['message' => 'Esta quiniela aún no está disponible.'], 404);
        }

        $misPredicciones = PrediccionQuiniela::where('id_quiniela', $quiniela->id)
            ->where('id_usuario', $request->user()->id)
            ->with('equipo')
            ->get()
            ->map(fn ($p) => [
                'id_equipo' => $p->id_equipo,
                'nombre' => $p->equipo->nombre_corto ?? $p->equipo->nombre,
                'escudo_url' => $p->equipo->escudo_url,
                'posicion_predicha' => $p->posicion_predicha,
                'puntos_obtenidos' => $p->puntos_obtenidos,
            ]);

        return response()->json([
            'data' => [
                'abierta' => $quiniela->abierta,
                'resuelta' => $quiniela->resuelta,
                'mis_predicciones' => $misPredicciones,
                'puntos_totales' => $quiniela->resuelta ? (int) $misPredicciones->sum('puntos_obtenidos') : null,
            ],
        ]);
    }

    public function guardar(Request $request, string $tipo)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $quiniela = QuinielaPosiciones::where('id_liga', $liga->id)->where('tipo', $tipo)->first();

        if (! $quiniela || ! $quiniela->abierta) {
            return response()->json(['message' => 'Esta quiniela no está abierta para predicciones ahora mismo.'], 422);
        }

        $totalEquipos = Equipo::count();

        $validated = $request->validate([
            'predicciones' => ['required', 'array', "size:{$totalEquipos}"],
            'predicciones.*.id_equipo' => ['required', 'distinct', 'exists:equipos,id'],
            'predicciones.*.posicion_predicha' => ['required', 'integer', 'min:1', "max:{$totalEquipos}", 'distinct'],
        ]);

        PrediccionQuiniela::where('id_quiniela', $quiniela->id)
            ->where('id_usuario', $request->user()->id)
            ->delete();

        foreach ($validated['predicciones'] as $prediccion) {
            PrediccionQuiniela::create([
                'id_quiniela' => $quiniela->id,
                'id_usuario' => $request->user()->id,
                'id_equipo' => $prediccion['id_equipo'],
                'posicion_predicha' => $prediccion['posicion_predicha'],
            ]);
        }

        return response()->json(['message' => 'Predicción guardada correctamente.']);
    }
}