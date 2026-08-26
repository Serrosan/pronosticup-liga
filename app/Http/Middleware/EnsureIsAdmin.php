<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! $request->user()->es_superadmin) {
            return response()->json(['message' => 'Acceso solo para administradores.'], 403);
        }

        return $next($request);
    }
}