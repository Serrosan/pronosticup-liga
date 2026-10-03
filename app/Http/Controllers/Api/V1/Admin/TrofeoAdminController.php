<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\TrofeoRequest;
use App\Http\Resources\TrofeoResource;
use App\Models\Trofeo;

class TrofeoAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return TrofeoResource::collection(Trofeo::orderBy('nombre')->get());
    }

    public function show(Trofeo $trofeo)
    {
        return $this->mostrarRecurso($trofeo);
    }

    public function store(TrofeoRequest $request)
    {
        return $this->crearRecurso($request, Trofeo::class, TrofeoResource::class);
    }

    public function update(TrofeoRequest $request, Trofeo $trofeo)
    {
        return $this->actualizarRecurso($request, $trofeo, TrofeoResource::class);
    }

    public function destroy(Trofeo $trofeo)
    {
        return $this->eliminarRecurso($trofeo, 'Trofeo');
    }
}