<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\CodigoCanjeRequest;
use App\Http\Resources\CodigoCanjeResource;
use App\Models\CodigoCanje;

class CodigoCanjeAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return CodigoCanjeResource::collection(
            CodigoCanje::with('tipoCarta')->withCount('canjes')->orderByDesc('id')->get()
        );
    }

    public function show(CodigoCanje $codigoCanje)
    {
        return $this->mostrarRecurso($codigoCanje);
    }

    public function store(CodigoCanjeRequest $request)
    {
        return $this->crearRecurso($request, CodigoCanje::class, CodigoCanjeResource::class);
    }

    public function update(CodigoCanjeRequest $request, CodigoCanje $codigoCanje)
    {
        return $this->actualizarRecurso($request, $codigoCanje, CodigoCanjeResource::class);
    }

    public function destroy(CodigoCanje $codigoCanje)
    {
        return $this->eliminarRecurso($codigoCanje, 'Código de canje');
    }
}