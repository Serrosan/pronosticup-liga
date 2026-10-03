<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArbitroRequest;
use App\Http\Resources\ArbitroResource;
use App\Models\Arbitro;

class ArbitroAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return ArbitroResource::collection(Arbitro::orderBy('nombre')->get());
    }

    public function show(Arbitro $arbitro)
    {
        return $this->mostrarRecurso($arbitro);
    }

    public function store(ArbitroRequest $request)
    {
        return $this->crearRecurso($request, Arbitro::class, ArbitroResource::class);
    }

    public function update(ArbitroRequest $request, Arbitro $arbitro)
    {
        return $this->actualizarRecurso($request, $arbitro, ArbitroResource::class);
    }

    public function destroy(Arbitro $arbitro)
    {
        return $this->eliminarRecurso($arbitro, 'Árbitro');
    }
}