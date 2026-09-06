<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionPuntos;
use App\Models\Liga;
use Illuminate\Http\Request;

class ConfiguracionPuntosAdminController extends Controller
{
    private array $reglas = [
        'puntos_signo' => ['required', 'integer', 'min:0'],
        'puntos_diferencia' => ['required', 'integer', 'min:0'],
        'puntos_exacto' => ['required', 'integer', 'min:0'],
        'bonus_pleno_7' => ['required', 'integer', 'min:0'],
        'bonus_pleno_8' => ['required', 'integer', 'min:0'],
        'bonus_pleno_9' => ['required', 'integer', 'min:0'],
        'bonus_pleno_10' => ['required', 'integer', 'min:0'],
        'puntos_gol_goleador' => ['required', 'integer', 'min:0'],
    ];

    public function global()
    {
        $config = ConfiguracionPuntos::firstOrCreate(['id_liga' => null]);

        return response()->json(['data' => $config]);
    }

    public function actualizarGlobal(Request $request)
    {
        $validated = $request->validate($this->reglas);
        $config = ConfiguracionPuntos::firstOrCreate(['id_liga' => null]);
        $config->update($validated);

        return response()->json(['data' => $config, 'message' => 'Configuración global actualizada.']);
    }

    public function paraLiga(Liga $liga)
    {
        $personalizado = ConfiguracionPuntos::where('id_liga', $liga->id)->first();
        $config = $personalizado ?? ConfiguracionPuntos::paraLiga($liga->id);

        return response()->json([
            'data' => $config,
            'personalizado' => ! is_null($personalizado),
        ]);
    }

    public function actualizarParaLiga(Request $request, Liga $liga)
    {
        $validated = $request->validate($this->reglas);

        $config = ConfiguracionPuntos::updateOrCreate(
            ['id_liga' => $liga->id],
            $validated
        );

        return response()->json(['data' => $config, 'message' => 'Configuración de la liga actualizada.']);
    }

    public function restaurarGlobal(Liga $liga)
    {
        ConfiguracionPuntos::where('id_liga', $liga->id)->delete();

        return response()->json(['message' => 'Restaurada la configuración global para esta liga.']);
    }
}