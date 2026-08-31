<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = $request->user()->notifications()->latest()->limit(30)->get();

        return response()->json([
            'data' => $notificaciones->map(fn ($n) => [
                'id' => $n->id,
                'tipo' => $n->data['tipo'] ?? null,
                'titulo' => $n->data['titulo'] ?? '',
                'mensaje' => $n->data['mensaje'] ?? '',
                'importante' => $n->data['importante'] ?? false,
                'leida' => ! is_null($n->read_at),
                'creada_en' => $n->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function noLeidas(Request $request)
    {
        $noLeidas = $request->user()->unreadNotifications()->get();

        return response()->json([
            'total' => $noLeidas->count(),
            'importante_pendiente' => $noLeidas->firstWhere('data.importante', true) ? [
                'id' => $noLeidas->firstWhere('data.importante', true)->id,
                'titulo' => $noLeidas->firstWhere('data.importante', true)->data['titulo'],
                'mensaje' => $noLeidas->firstWhere('data.importante', true)->data['mensaje'],
            ] : null,
        ]);
    }

    public function marcarLeida(Request $request, string $id)
    {
        $notificacion = $request->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();

        return response()->json(['message' => 'Marcada como leída.']);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Todas marcadas como leídas.']);
    }
}