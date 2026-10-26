<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ImagenService;
use App\Services\SesionesUsuarioService;
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

    /**
     * Cambiar la contraseña cierra además la sesión en los demás dispositivos:
     * si alguien entró con la contraseña vieja, se queda fuera (también su
     * "Mantener la sesión iniciada" y sus avisos al móvil).
     */
    public function updatePassword(Request $request, SesionesUsuarioService $sesiones)
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'endpoint' => ['nullable', 'string', 'max:2000'],
        ]);

        $usuario = $request->user();

        if (! Hash::check($validated['current_password'], $usuario->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta.'], 422);
        }

        $usuario->update(['password' => Hash::make($validated['password'])]);
        $sesiones->recordarContrasenaNueva($request, $usuario);
        $resultado = $sesiones->cerrarLasDemas($request, $usuario, $validated['endpoint'] ?? null);

        return response()->json(['message' => 'Contraseña actualizada. '.$sesiones->resumen($resultado)]);
    }

    /** POST /profile/cerrar-otras-sesiones — pide la contraseña para confirmar. */
    public function cerrarOtrasSesiones(Request $request, SesionesUsuarioService $sesiones)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'endpoint' => ['nullable', 'string', 'max:2000'],
        ]);

        $usuario = $request->user();

        if (! Hash::check($validated['password'], $usuario->password)) {
            return response()->json(['message' => 'La contraseña no es correcta.'], 422);
        }

        $resultado = $sesiones->cerrarLasDemas($request, $usuario, $validated['endpoint'] ?? null);

        return response()->json(['message' => $sesiones->resumen($resultado), 'data' => $resultado]);
    }

    public function uploadAvatar(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $ruta = $request->file('avatar')->store('avatars', 'public');
        ImagenService::comprimir(Storage::disk('public')->path($ruta));

        $request->user()->update(['avatar_url' => Storage::url($ruta)]);

        return new UserResource($request->user()->fresh());
    }

    public function updatePreferenciasEmail(Request $request)
    {
        $validated = $request->validate([
            'recibir_email_recordatorios' => ['required', 'boolean'],
            'recibir_email_puntos' => ['required', 'boolean'],
        ]);

        $request->user()->update($validated);

        return new UserResource($request->user()->fresh());
    }
}