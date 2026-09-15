<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NovedadRequest;
use App\Models\Novedad;

class NovedadAdminController extends Controller
{
    public function index()
    {
        $novedades = Novedad::with('liga')->orderByDesc('id')->get()->map(function ($novedad) {
            $datos = $novedad->toArray();
            $datos['liga_nombre'] = $novedad->liga->nombre ?? 'Global (todas)';
            return $datos;
        });

        return response()->json(['data' => $novedades]);
    }

    public function show(Novedad $novedad)
    {
        return response()->json(['data' => $novedad]);
    }

    public function store(NovedadRequest $request)
    {
        $novedad = Novedad::create($request->validated());
        return response()->json(['data' => $novedad]);
    }

    public function update(NovedadRequest $request, Novedad $novedad)
    {
        $novedad->update($request->validated());
        return response()->json(['data' => $novedad]);
    }

    public function destroy(Novedad $novedad)
    {
        $novedad->delete();
        return response()->json(['message' => 'Novedad eliminada.']);
    }
}