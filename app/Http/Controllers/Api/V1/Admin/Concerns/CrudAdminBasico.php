<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CrudAdminBasico
{
    protected function mostrarRecurso(Model $modelo): JsonResponse
    {
        return response()->json(['data' => $modelo]);
    }

    protected function crearRecurso(Request $request, string $modeloClase, string $resourceClase)
    {
        $recurso = $modeloClase::create($request->validated());

        return new $resourceClase($recurso);
    }

    protected function actualizarRecurso(Request $request, Model $modelo, string $resourceClase)
    {
        $modelo->update($request->validated());

        return new $resourceClase($modelo);
    }

    protected function eliminarRecurso(Model $modelo, string $nombreSingular): JsonResponse
    {
        $modelo->delete();

        return response()->json(['message' => "{$nombreSingular} eliminado."]);
    }
}