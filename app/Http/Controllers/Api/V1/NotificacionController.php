<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $porPagina = 15;
        $pagina = (int) $request->query('pagina', 1);

        $consulta = $request->user()->notifications()->latest();
        $total = $consulta->count();

        $notificaciones = $consulta
            ->skip(($pagina - 1) * $porPagina)
            ->take($porPagina)
            ->get();

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
            'meta' => [
                'pagina' => $pagina,
                'total' => $total,
                'hay_mas' => ($pagina * $porPagina) < $total,
            ],
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

    public function destroy(Request $request, string $id)
    {
        $notificacion = $request->user()->notifications()->findOrFail($id);
        $notificacion->delete();

        return response()->json(['message' => 'Notificación eliminada.']);
    }

    public function borrarLeidas(Request $request)
    {
        $borradas = $request->user()->readNotifications()->count();
        $request->user()->readNotifications()->delete();

        return response()->json(['message' => "{$borradas} notificación(es) eliminadas."]);
    }
}