<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FicharJugadorRequest;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;

class FichajeAdminController extends Controller
{
    public function fichar(FicharJugadorRequest $request, Jugador $jugador)
    {
        $validated = $request->validated();
        $temporada = Temporada::first();

        $plantillaActual = PlantillaTemporada::where('id_jugador', $jugador->id)
            ->where('id_temporada', $temporada->id)
            ->whereNull('fecha_salida')
            ->first();

        if ($plantillaActual) {
            if ($plantillaActual->id_equipo === $validated['id_equipo_nuevo']) {
                return response()->json(['message' => 'El jugador ya pertenece a ese equipo.'], 422);
            }

            if ($validated['fecha_fichaje'] < $plantillaActual->fecha_incorporacion) {
                return response()->json([
                    'message' => "La fecha de fichaje no puede ser anterior a su incorporación actual ({$plantillaActual->fecha_incorporacion}).",
                ], 422);
            }
        }

        if (! empty($validated['dorsal'])) {
            $dorsalOcupado = PlantillaTemporada::where('id_equipo', $validated['id_equipo_nuevo'])
                ->where('id_temporada', $temporada->id)
                ->where('dorsal', $validated['dorsal'])
                ->whereNull('fecha_salida')
                ->exists();

            if ($dorsalOcupado) {
                return response()->json(['message' => "El dorsal {$validated['dorsal']} ya lo lleva otro jugador activo de ese equipo."], 422);
            }
        }

        if ($plantillaActual) {
            $plantillaActual->update(['fecha_salida' => $validated['fecha_fichaje']]);
        }

        $nuevaPlantilla = PlantillaTemporada::create([
            'id_jugador' => $jugador->id,
            'id_equipo' => $validated['id_equipo_nuevo'],
            'id_temporada' => $temporada->id,
            'dorsal' => $validated['dorsal'] ?? null,
            'fecha_incorporacion' => $validated['fecha_fichaje'],
            'fecha_salida' => null,
        ]);

        return response()->json([
            'message' => 'Fichaje registrado correctamente.',
            'data' => $nuevaPlantilla,
        ]);
    }

    public function historial(Jugador $jugador)
    {
        $historial = PlantillaTemporada::where('id_jugador', $jugador->id)
            ->with('equipo')
            ->orderByDesc('fecha_incorporacion')
            ->get()
            ->map(fn ($p) => [
                'equipo' => $p->equipo->nombre_corto ?? $p->equipo->nombre,
                'escudo_url' => $p->equipo->escudo_url,
                'dorsal' => $p->dorsal,
                'fecha_incorporacion' => $p->fecha_incorporacion,
                'fecha_salida' => $p->fecha_salida,
                'actual' => is_null($p->fecha_salida),
            ]);

        return response()->json(['data' => $historial]);
    }

    public function darDeBaja(Jugador $jugador)
    {
        $temporada = Temporada::first();

        PlantillaTemporada::where('id_jugador', $jugador->id)
            ->where('id_temporada', $temporada->id)
            ->whereNull('fecha_salida')
            ->update(['fecha_salida' => now()->toDateString()]);

        $jugador->update(['dado_de_baja_en' => now()]);

        return response()->json(['message' => 'Jugador dado de baja (borrado lógico, historial conservado).']);
    }

    public function reactivar(Jugador $jugador)
    {
        $jugador->update(['dado_de_baja_en' => null]);

        return response()->json(['message' => 'Jugador reactivado. Recuerda ficharlo por un equipo si va a volver a jugar.']);
    }
}