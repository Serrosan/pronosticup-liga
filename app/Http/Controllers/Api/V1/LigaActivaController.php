<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class LigaActivaController extends Controller
{
    public function set(Request $request)
    {
        $validated = $request->validate(['liga_id' => ['required', 'exists:ligas,id']]);

        $esMiembro = $request->user()->ligas()->where('ligas.id', $validated['liga_id'])->exists();

        if (! $esMiembro) {
            return response()->json(['message' => 'No perteneces a esa liga.'], 403);
        }

        $request->user()->update(['liga_activa_id' => $validated['liga_id']]);

        return new UserResource($request->user()->fresh());
    }
}