<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'nombre_visible' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return new UserResource($request->user()->fresh());
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta.'], 422);
        }

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function uploadAvatar(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $ruta = $request->file('avatar')->store('avatars', 'public');

        $request->user()->update(['avatar_url' => Storage::url($ruta)]);

        return new UserResource($request->user()->fresh());
    }
}