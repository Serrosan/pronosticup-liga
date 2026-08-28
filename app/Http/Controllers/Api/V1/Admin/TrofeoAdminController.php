<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrofeoRequest;
use App\Http\Resources\TrofeoResource;
use App\Models\Trofeo;

class TrofeoAdminController extends Controller
{
    public function index()
    {
        return TrofeoResource::collection(Trofeo::orderBy('nombre')->get());
    }

    public function show(Trofeo $trofeo)
    {
        return response()->json(['data' => $trofeo]);
    }

    public function store(TrofeoRequest $request)
    {
        $trofeo = Trofeo::create($request->validated());

        return new TrofeoResource($trofeo);
    }

    public function update(TrofeoRequest $request, Trofeo $trofeo)
    {
        $trofeo->update($request->validated());

        return new TrofeoResource($trofeo);
    }

    public function destroy(Trofeo $trofeo)
    {
        $trofeo->delete();

        return response()->json(['message' => 'Trofeo eliminado.']);
    }
}