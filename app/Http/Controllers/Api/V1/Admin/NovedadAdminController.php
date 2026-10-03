<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\NovedadRequest;
use App\Models\Novedad;

class NovedadAdminController extends Controller
{
    use CrudAdminBasico;

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
        return $this->mostrarRecurso($novedad);
    }

    public function store(NovedadRequest $request)
    {
        return $this->crearRecursoPlano($request, Novedad::class);
    }

    public function update(NovedadRequest $request, Novedad $novedad)
    {
        return $this->actualizarRecursoPlano($request, $novedad);
    }

    public function destroy(Novedad $novedad)
    {
        return $this->eliminarRecurso($novedad, 'Novedad');
    }
}