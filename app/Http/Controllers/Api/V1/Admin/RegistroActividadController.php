<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartaUsuario;
use App\Models\RegistroActividad;
use Illuminate\Http\Request;

class RegistroActividadController extends Controller
{
    public function index(Request $request)
    {
        $registrosRaw = RegistroActividad::with('usuario')
            ->orderByDesc('creado_en')
            ->limit(200)
            ->get();

        $idsCartas = $registrosRaw->where('modelo', 'CartaUsuario')->pluck('id_registro')->unique()->filter();
        $cartas = CartaUsuario::whereIn('id', $idsCartas)
            ->with(['tipoCarta.categoria', 'usuario', 'usuarioObjetivo', 'partido.equipoLocal', 'partido.equipoVisitante'])
            ->get()
            ->keyBy('id');

        $registros = $registrosRaw->map(function ($r) use ($cartas) {
            $detalle = null;

            if ($r->modelo === 'CartaUsuario') {
                $carta = $cartas->get($r->id_registro);

                if ($carta) {
                    $partes = [];

                    $nombreCarta = $carta->tipoCarta->nombre ?? 'Carta eliminada del catálogo';
                    $categoria = $carta->tipoCarta->categoria->nombre ?? '—';
                    $rareza = $carta->tipoCarta->rareza ?? '—';
                    $nombreDueno = $carta->usuario->nombre_visible ?? $carta->usuario->name ?? 'usuario desconocido';

                    $partes[] = "\"{$nombreCarta}\" ({$categoria} · {$rareza}) de {$nombreDueno}";
                    $partes[] = "origen: {$carta->origen}";
                    $partes[] = "jornada {$carta->jornada_obtenida}";
                    $partes[] = "estado actual: {$carta->estado}";

                    if ($carta->partido) {
                        $partes[] = "jugada sobre: {$carta->partido->equipoLocal->nombre} vs {$carta->partido->equipoVisitante->nombre}";
                    }

                    if ($carta->usuarioObjetivo) {
                        $nombreObjetivo = $carta->usuarioObjetivo->nombre_visible ?? $carta->usuarioObjetivo->name;
                        $partes[] = "objetivo: {$nombreObjetivo}";
                    }

                    if (! is_null($carta->puntos_generados)) {
                        $partes[] = "puntos generados: {$carta->puntos_generados}";
                    }

                    $detalle = implode(' · ', $partes);
                } else {
                    $detalle = 'Carta ya eliminada de la base de datos';
                }
            }

            return [
                'id' => $r->id,
                'usuario' => $r->usuario?->nombre_visible ?? $r->usuario?->name ?? 'Sistema',
                'accion' => $r->accion,
                'modelo' => $r->modelo,
                'id_registro' => $r->id_registro,
                'detalle' => $detalle,
                'cambios' => $r->cambios,
                'creado_en' => $r->creado_en->toIso8601String(),
            ];
        });

        // --- Resumen rápido de salud del sistema de Cartas ---
        $resumen = [
            'en_mano' => CartaUsuario::where('estado', 'en_mano')->count(),
            'jugadas_sin_resolver' => CartaUsuario::where('estado', 'jugada')->count(),
            'resueltas_hoy' => CartaUsuario::whereIn('estado', ['resuelta_cumplida', 'resuelta_no_cumplida'])
                ->whereDate('updated_at', now()->toDateString())
                ->count(),
        ];

        // --- Alerta: cartas "jugada" desde hace más de 1 semana sin resolverse —
        // normalmente síntoma de una jornada que se quedó a medias sin cerrar.
        $cartasAtascadas = CartaUsuario::where('estado', 'jugada')
            ->where('jugada_en', '<', now()->subWeek())
            ->with(['tipoCarta', 'usuario'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'carta' => $c->tipoCarta->nombre ?? '—',
                'usuario' => $c->usuario->nombre_visible ?? $c->usuario->name ?? '—',
                'dias_desde_jugada' => (int) now()->diffInDays($c->jugada_en),
                'jornada' => $c->jornada_obtenida,
            ]);

        return response()->json([
            'data' => $registros,
            'meta' => [
                'resumen' => $resumen,
                'alertas' => $cartasAtascadas,
            ],
        ]);
    }
}