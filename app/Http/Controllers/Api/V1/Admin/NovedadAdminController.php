<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NovedadRequest;
use App\Models\Novedad;

class NovedadAdminController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Novedad::orderByDesc('id')->get()]);
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