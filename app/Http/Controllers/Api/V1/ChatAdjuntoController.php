<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatAdjuntoController extends Controller
{
    public function subirImagen(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'imagen' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $nombreSeguro = Str::random(40).'.'.$request->file('imagen')->getClientOriginalExtension();
        $ruta = $request->file('imagen')->storeAs('chat', $nombreSeguro, 'public');

        return response()->json(['url' => url(Storage::url($ruta))]);
    }

    public function subirAudio(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimes:webm,ogg,mp3,wav,m4a', 'max:5120'],
        ]);

        $nombreSeguro = Str::random(40).'.'.$request->file('audio')->getClientOriginalExtension();
        $ruta = $request->file('audio')->storeAs('chat', $nombreSeguro, 'public');

        return response()->json(['url' => url(Storage::url($ruta))]);
    }
}