<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagenAdminController extends Controller
{
    public function subir(Request $request)
    {
        $validated = $request->validate([
            'imagen' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
                'dimensions:min_width=50,min_height=50,max_width=4000,max_height=4000',
            ],
            'carpeta' => ['required', 'string', 'in:ligas,arbitros,trofeos,jugadores,equipos,entrenadores,estadios'],
        ]);

        $nombreSeguro = Str::random(40).'.'.$request->file('imagen')->getClientOriginalExtension();

        $ruta = $request->file('imagen')->storeAs(
            $validated['carpeta'],
            $nombreSeguro,
            'public'
        );

        return response()->json(['url' => url(Storage::url($ruta))]);
    }
}