<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArbitroRequest;
use App\Http\Resources\ArbitroResource;
use App\Models\Arbitro;

class ArbitroAdminController extends Controller
{
    public function index()
    {
        return ArbitroResource::collection(Arbitro::orderBy('nombre')->get());
    }

    public function show(Arbitro $arbitro)
    {
        return response()->json(['data' => $arbitro]);
    }

    public function store(ArbitroRequest $request)
    {
        $arbitro = Arbitro::create($request->validated());

        return new ArbitroResource($arbitro);
    }

    public function update(ArbitroRequest $request, Arbitro $arbitro)
    {
        $arbitro->update($request->validated());

        return new ArbitroResource($arbitro);
    }

    public function destroy(Arbitro $arbitro)
    {
        $arbitro->delete();

        return response()->json(['message' => 'Árbitro eliminado.']);
    }
}