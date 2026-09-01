<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Liga;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'codigo_liga' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (! empty($validated['codigo_liga'])) {
            $liga = Liga::where('codigo_acceso', strtoupper($validated['codigo_liga']))->first();
            if ($liga) {
                $liga->usuarios()->attach($user->id, ['rol' => 'Miembro']);
            }
        }

        event(new Registered($user));
        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return (new UserResource($user))
            ->additional(['message' => 'Cuenta creada. Revisa tu email para activarla.'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $request->session()->regenerate();
        $user = Auth::user();

        if (is_null($user->activado_en)) {
            Auth::logout();
            return response()->json(['message' => 'Debes activar tu cuenta antes de entrar.'], 403);
        }

        if (! is_null($user->desactivada_en)) {
            Auth::logout();
            return response()->json(['message' => 'Esta cuenta ha sido desactivada. Contacta con el administrador si quieres reactivarla.'], 403);
        }

        return new UserResource($user);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'Si ese email existe en nuestro sistema, te hemos enviado un enlace para restablecer la contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json(['message' => 'El enlace no es válido o ha caducado.'], 422);
        }

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}