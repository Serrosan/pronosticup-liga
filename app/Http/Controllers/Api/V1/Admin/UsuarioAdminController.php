<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioAdminController extends Controller
{
    public function index()
    {
        $usuarios = User::with('ligas')->orderBy('name')->get();

        return response()->json([
            'data' => $usuarios->map(fn ($u) => [
                'id' => $u->id,
                'nombre' => $u->nombre_visible ?? $u->name,
                'email' => $u->email,
                'es_superadmin' => (bool) $u->es_superadmin,
                'activado' => ! is_null($u->activado_en),
                'ligas' => $u->ligas->pluck('nombre'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8'],
        ]);

        // Un usuario creado a mano por el admin queda activado directamente,
        // sin pasar por el circuito de verificación de email.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'activado_en' => now(),
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Usuario creado y activado.', 'data' => ['id' => $user->id]], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return response()->json(['message' => 'Usuario actualizado.']);
    }

    public function activar(User $user)
    {
        $user->forceFill(['activado_en' => now(), 'email_verified_at' => now()])->save();

        return response()->json(['message' => 'Usuario activado.']);
    }

    public function actualizarRol(Request $request, User $user)
    {
        $validated = $request->validate(['es_superadmin' => ['required', 'boolean']]);

        $user->update($validated);

        return response()->json(['message' => 'Rol actualizado.']);
    }
}