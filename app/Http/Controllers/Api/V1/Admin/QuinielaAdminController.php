<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\LaLigaStandingsController;
use App\Models\Equipo;
use App\Models\PrediccionQuiniela;
use App\Models\QuinielaPosiciones;
use Illuminate\Http\Request;

class QuinielaAdminController extends Controller
{
    private array $puntosPorTipo = [
        'completa' => ['exacta' => 5, 'max' => 4],
        'primera_mitad' => ['exacta' => 3, 'max' => 2],
        'segunda_mitad' => ['exacta' => 3, 'max' => 2],
    ];

    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        $quinielas = collect(['completa', 'primera_mitad', 'segunda_mitad'])->map(function ($tipo) use ($liga) {
            $q = QuinielaPosiciones::firstOrCreate(['id_liga' => $liga->id, 'tipo' => $tipo]);

            return [
                'id' => $q->id,
                'tipo' => $tipo,
                'abierta' => $q->abierta,
                'resuelta' => $q->resuelta,
                'total_predicciones' => $q->predicciones()->distinct('id_usuario')->count('id_usuario'),
            ];
        });

        return response()->json(['data' => $quinielas]);
    }

    public function abrir(QuinielaPosiciones $quiniela)
    {
        if ($quiniela->resuelta) {
            return response()->json(['message' => 'Esta quiniela ya está resuelta, no se puede reabrir.'], 422);
        }

        $quiniela->update(['abierta' => true]);

        return response()->json(['message' => 'Quiniela abierta.']);
    }

    public function cerrarSinResolver(QuinielaPosiciones $quiniela)
    {
        $quiniela->update(['abierta' => false]);

        return response()->json(['message' => 'Quiniela cerrada para nuevas predicciones.']);
    }

    public function resolver(Request $request, QuinielaPosiciones $quiniela)
    {
        if ($quiniela->resuelta) {
            return response()->json(['message' => 'Esta quiniela ya estaba resuelta.'], 409);
        }

        $liga = $request->user()->ligaActiva;
        $hastaJornada = $quiniela->tipo === 'primera_mitad' ? 18 : null;

        $clasificacionReal = LaLigaStandingsController::clasificacionGeneral($liga->id_temporada, $hastaJornada);
        $posicionRealPorEquipo = $clasificacionReal->pluck('id')->flip()->map(fn ($indice) => $indice + 1);

        $maximo = $this->puntosPorTipo[$quiniela->tipo]['exacta'];

        $predicciones = $quiniela->predicciones;

        foreach ($predicciones as $prediccion) {
            $posicionReal = $posicionRealPorEquipo->get($prediccion->id_equipo);

            if (is_null($posicionReal)) {
                $prediccion->update(['puntos_obtenidos' => 0]);
                continue;
            }

            $diferencia = abs($posicionReal - $prediccion->posicion_predicha);
            $puntos = max(0, $maximo - $diferencia);

            $prediccion->update(['puntos_obtenidos' => $puntos]);
        }

        $quiniela->update(['resuelta' => true, 'abierta' => false, 'resuelta_en' => now()]);

        return response()->json(['message' => 'Quiniela resuelta y puntos calculados.']);
    }
}