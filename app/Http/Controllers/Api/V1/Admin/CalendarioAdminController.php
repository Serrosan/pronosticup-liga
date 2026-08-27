<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarioAdminController extends Controller
{
    public function index(Request $request)
    {
        $jornada = (int) $request->query('jornada', 1);

        $partidos = CalendarioPartido::where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get();

        return response()->json([
            'data' => $partidos->map(fn ($p) => [
                'id' => $p->id,
                'jornada' => $p->jornada,
                'equipo_local' => $p->equipoLocal->nombre,
                'equipo_visitante' => $p->equipoVisitante->nombre,
                'escudo_local' => $p->equipoLocal->escudo_url,
                'escudo_visitante' => $p->equipoVisitante->escudo_url,
                'horario_estimado' => $p->horario_estimado?->format('Y-m-d\TH:i'),
                'estado' => $p->estado,
                'goles_casa' => $p->goles_casa,
                'goles_fuera' => $p->goles_fuera,
            ]),
        ]);
    }

    public function update(Request $request, CalendarioPartido $partido)
    {
        $validated = $request->validate([
            'horario_estimado' => ['required', 'date'],
            'estado' => ['required', Rule::in(['Programado', 'Jugado', 'Aplazado'])],
            'goles_casa' => ['nullable', 'integer', 'min:0'],
            'goles_fuera' => ['nullable', 'integer', 'min:0'],
        ]);

        $partido->update($validated);

        return response()->json(['message' => 'Partido actualizado.']);
    }
}