<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MensajeChatRequest;
use App\Models\MensajeChat;
use App\Notifications\MencionadoEnChat;
use App\Notifications\MensajeReaccionado;
use App\Notifications\MensajeRespondido;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $mensajes = MensajeChat::where('id_liga', $liga->id)
            ->with(['usuario', 'mensajeRespondido.usuario'])
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => $this->formatear($m));

        $mensajeFijado = MensajeChat::where('id_liga', $liga->id)
            ->where('fijado', true)
            ->with('usuario')
            ->latest()
            ->first();

        return response()->json([
            'data' => $mensajes,
            'meta' => [
                'total_miembros' => $liga->usuarios()->count(),
                'mensaje_fijado' => $mensajeFijado ? $this->formatear($mensajeFijado) : null,
            ],
        ]);
    }

    public function store(MensajeChatRequest $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $validated = $request->validated();
        $mensajeRespondido = null;

        if (! empty($validated['id_mensaje_respondido'])) {
            $mensajeRespondido = MensajeChat::where('id', $validated['id_mensaje_respondido'])
                ->where('id_liga', $liga->id)
                ->first();

            if (! $mensajeRespondido) {
                return response()->json(['message' => 'No puedes responder a ese mensaje.'], 403);
            }
        }

        $mensaje = MensajeChat::create([
            'id_liga' => $liga->id,
            'id_usuario' => $request->user()->id,
            'texto' => $validated['texto'] ?? null,
            'tipo' => $validated['tipo'],
            'adjunto_url' => $validated['adjunto_url'] ?? null,
            'reacciones' => [],
            'id_mensaje_respondido' => $mensajeRespondido?->id,
        ]);

        $mensaje->load(['usuario', 'mensajeRespondido.usuario']);

        if ($mensajeRespondido && $mensajeRespondido->id_usuario !== $request->user()->id) {
            $nombreQuienResponde = $mensaje->usuario->nombre_visible ?? $mensaje->usuario->name;
            $mensajeRespondido->usuario?->notify(new MensajeRespondido($nombreQuienResponde));
        }

        if ($validated['tipo'] === 'texto' && ! empty($validated['texto'])) {
            $this->notificarMenciones($validated['texto'], $liga, $request->user()->id, $mensaje->usuario);
        }

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
        $seAgrego = false;

        if ($reacciones[$emoji]->contains($usuarioId)) {
            $reacciones[$emoji] = $reacciones[$emoji]->reject(fn ($id) => $id === $usuarioId)->values();
        } else {
            $reacciones[$emoji] = $reacciones[$emoji]->push($usuarioId);
            $seAgrego = true;
        }

        $reacciones[$emoji] = $reacciones[$emoji]->values()->all();

        $mensajeChat->update(['reacciones' => $reacciones]);
        $mensajeChat->load(['usuario', 'mensajeRespondido.usuario']);

        if ($seAgrego && $mensajeChat->id_usuario !== $usuarioId) {
            $nombreQuienReacciona = $request->user()->nombre_visible ?? $request->user()->name;
            $mensajeChat->usuario?->notify(new MensajeReaccionado($nombreQuienReacciona, $emoji));
        }

        return response()->json(['data' => $this->formatear($mensajeChat)]);
    }

    public function fijar(Request $request, MensajeChat $mensajeChat)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga || $mensajeChat->id_liga !== $liga->id) {
            return response()->json(['message' => 'No tienes acceso a este mensaje.'], 403);
        }

        $esAdmin = $liga->usuarios()
            ->where('id_usuario', $request->user()->id)
            ->wherePivot('rol', 'Admin')
            ->exists();

        if (! $esAdmin) {
            return response()->json(['message' => 'Solo el admin de la liga puede fijar mensajes.'], 403);
        }

        MensajeChat::where('id_liga', $liga->id)->where('fijado', true)->update(['fijado' => false]);

        $mensajeChat->update(['fijado' => ! $mensajeChat->fijado]);

        return response()->json(['data' => $this->formatear($mensajeChat->fresh())]);
    }

    /**
     * Busca @menciones en el texto (nombres de miembros de la liga, o "@todos") y
     * notifica solo por campana a cada persona mencionada — nunca al propio autor.
     * Los nombres compuestos se comprueban primero (de más largo a más corto) para
     * que "@Juan Carlos" se detecte entero antes que solo "@Juan".
     */
    private function notificarMenciones(string $texto, $liga, int $autorId, $autor): void
    {
        $normalizar = fn ($t) => Str::of($t)->lower()->ascii()->toString();
        $textoNormalizado = $normalizar($texto);
        $nombreQuienMenciona = $autor->nombre_visible ?? $autor->name;

        if (Str::contains($textoNormalizado, '@todos')) {
            $miembros = $liga->usuarios()->where('users.id', '!=', $autorId)->get();
            foreach ($miembros as $miembro) {
                $miembro->notify(new MencionadoEnChat($nombreQuienMenciona));
            }
            return;
        }

        $miembros = $liga->usuarios()->where('users.id', '!=', $autorId)->get(['users.id', 'users.name', 'users.nombre_visible']);
        $ordenadosPorLongitud = $miembros->sortByDesc(fn ($u) => strlen($u->nombre_visible ?? $u->name));

        $yaNotificados = [];

        foreach ($ordenadosPorLongitud as $miembro) {
            if (in_array($miembro->id, $yaNotificados)) {
                continue;
            }

            $nombreNormalizado = $normalizar($miembro->nombre_visible ?? $miembro->name);

            if (Str::contains($textoNormalizado, '@'.$nombreNormalizado)) {
                $miembro->notify(new MencionadoEnChat($nombreQuienMenciona));
                $yaNotificados[] = $miembro->id;
            }
        }
    }

    private function formatear(MensajeChat $mensaje): array
    {
        return [
            'id' => $mensaje->id,
            'texto' => $mensaje->texto,
            'tipo' => $mensaje->tipo,
            'adjunto_url' => $mensaje->adjunto_url,
            'fijado' => $mensaje->fijado,
            'usuario' => [
                'id' => $mensaje->usuario->id,
                'nombre' => $mensaje->usuario->nombre_visible ?? $mensaje->usuario->name,
                'avatar_url' => $mensaje->usuario->avatar_url ? url($mensaje->usuario->avatar_url) : null,
            ],
            'reacciones' => $mensaje->reacciones ?? [],
            'creado_en' => $mensaje->created_at->toIso8601String(),
            'respondido_a' => $mensaje->mensajeRespondido ? [
                'id' => $mensaje->mensajeRespondido->id,
                'usuario' => $mensaje->mensajeRespondido->usuario->nombre_visible ?? $mensaje->mensajeRespondido->usuario->name,
                'texto' => $mensaje->mensajeRespondido->tipo === 'texto'
                    ? $mensaje->mensajeRespondido->texto
                    : ($mensaje->mensajeRespondido->tipo === 'imagen' ? '📷 Imagen' : '🎤 Nota de voz'),
            ] : null,
        ];
    }
}