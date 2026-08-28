<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MensajeChatRequest;
use App\Models\MensajeChat;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $mensajes = MensajeChat::where('id_liga', $liga->id)
            ->with('usuario')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => $this->formatear($m));

        return response()->json([
            'data' => $mensajes,
            'meta' => ['total_miembros' => $liga->usuarios()->count()],
        ]);
    }

    public function store(MensajeChatRequest $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validated();

        $mensaje = MensajeChat::create([
            'id_liga' => $liga->id,
            'id_usuario' => $request->user()->id,
            'texto' => $validated['texto'] ?? null,
            'tipo' => $validated['tipo'],
            'adjunto_url' => $validated['adjunto_url'] ?? null,
            'reacciones' => [],
        ]);

        $mensaje->load('usuario');

        return response()->json(['data' => $this->formatear($mensaje)]);
    }

    public function reaccionar(Request $request, MensajeChat $mensajeChat)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga || $mensajeChat->id_liga !== $liga->id) {
            return response()->json(['message' => 'No tienes acceso a este mensaje.'], 403);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'in:👍,🔥,😂,😢,🎉'],
        ]);

        $reacciones = $mensajeChat->reacciones ?? [];
        $emoji = $validated['emoji'];
        $usuarioId = $request->user()->id;

        $reacciones[$emoji] = collect($reacciones[$emoji] ?? []);

        if ($reacciones[$emoji]->contains($usuarioId)) {
            $reacciones[$emoji] = $reacciones[$emoji]->reject(fn ($id) => $id === $usuarioId)->values();
        } else {
            $reacciones[$emoji] = $reacciones[$emoji]->push($usuarioId);
        }

        $reacciones[$emoji] = $reacciones[$emoji]->values()->all();

        $mensajeChat->update(['reacciones' => $reacciones]);
        $mensajeChat->load('usuario');

        return response()->json(['data' => $this->formatear($mensajeChat)]);
    }

    private function formatear(MensajeChat $mensaje): array
    {
        return [
            'id' => $mensaje->id,
            'texto' => $mensaje->texto,
            'tipo' => $mensaje->tipo,
            'adjunto_url' => $mensaje->adjunto_url,
            'usuario' => [
                'id' => $mensaje->usuario->id,
                'nombre' => $mensaje->usuario->nombre_visible ?? $mensaje->usuario->name,
                'avatar_url' => $mensaje->usuario->avatar_url ? url($mensaje->usuario->avatar_url) : null,
            ],
            'reacciones' => $mensaje->reacciones ?? [],
            'creado_en' => $mensaje->created_at->toIso8601String(),
        ];
    }
}