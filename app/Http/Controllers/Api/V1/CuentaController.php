<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CuentaController extends Controller
{
    public function desactivar(Request $request)
    {
        $validated = $request->validate(['password' => ['required']]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 422);
        }

        $request->user()->update(['desactivada_en' => now()]);
        $request->user()->tokens()->delete();
        auth()->guard('web')->logout();

        return response()->json(['message' => 'Cuenta desactivada.']);
    }
}