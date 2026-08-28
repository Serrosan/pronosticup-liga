<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Services\ParserComentariosPartido;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventoPartidoAdminController extends Controller
{
    public function interpretar(Request $request, ParserComentariosPartido $parser)
    {
        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
            'texto' => ['required', 'string'],
        ]);

        $partido = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->findOrFail($validated['id_partido']);

        $jugadoresLocal = $this->jugadoresDeEquipo($partido->id_equipo_local);
        $jugadoresVisitante = $this->jugadoresDeEquipo($partido->id_equipo_visitante);

        $eventos = $parser->parse($validated['texto']);

        $eventosEnriquecidos = collect($eventos)->map(function ($evento) use ($partido, $jugadoresLocal, $jugadoresVisitante) {
            $esLocal = $this->coincideEquipo($evento['equipo_texto'], $partido->equipoLocal->nombre, $partido->equipoLocal->nombre_corto);
            $poolJugadores = $esLocal ? $jugadoresLocal : $jugadoresVisitante;

            $evento['id_equipo'] = $esLocal ? $partido->id_equipo_local : $partido->id_equipo_visitante;
            $evento['id_jugador'] = $this->buscarJugador($evento['jugador_texto'], $poolJugadores);
            $evento['id_jugador_relacionado'] = $evento['jugador_relacionado_texto']
                ? $this->buscarJugador($evento['jugador_relacionado_texto'], $poolJugadores)
                : null;

            return $evento;
        });

        return response()->json(['data' => $eventosEnriquecidos->values()]);
    }

    public function guardar(Request $request)
    {
        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
            'eventos' => ['required', 'array'],
            'eventos.*.id_jugador' => ['required', 'exists:jugadores,id'],
            'eventos.*.id_equipo' => ['required', 'exists:equipos,id'],
            'eventos.*.minuto' => ['required', 'string'],
            'eventos.*.tipo_evento' => ['required', 'in:gol,tarjeta_amarilla,tarjeta_roja,sustitucion'],
            'eventos.*.id_jugador_relacionado' => ['nullable', 'exists:jugadores,id'],
        ]);

        foreach ($validated['eventos'] as $evento) {
            EventoPartido::create([
                'id_partido' => $validated['id_partido'],
                'id_jugador' => $evento['id_jugador'],
                'id_equipo' => $evento['id_equipo'],
                'minuto' => $evento['minuto'],
                'tipo_evento' => $evento['tipo_evento'],
                'id_jugador_relacionado' => $evento['id_jugador_relacionado'] ?? null,
            ]);
        }

        return response()->json(['message' => count($validated['eventos']).' eventos guardados.']);
    }

    private function jugadoresDeEquipo(int $idEquipo)
    {
        return Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $idEquipo)->whereNull('fecha_salida'))
            ->whereNull('dado_de_baja_en')
            ->get(['id', 'nombre', 'apellidos', 'nombre_camiseta']);
    }

    private function coincideEquipo(string $textoParseado, string $nombreCompleto, ?string $nombreCorto): bool
    {
        $normalizado = Str::of($textoParseado)->lower()->ascii()->toString();
        return Str::contains(Str::of($nombreCompleto)->lower()->ascii()->toString(), $normalizado)
            || Str::contains($normalizado, Str::of($nombreCorto ?? '')->lower()->ascii()->toString());
    }

    private function buscarJugador(string $textoParseado, $pool): ?int
    {
        $normalizado = Str::of($textoParseado)->lower()->ascii()->toString();

        // Intento 1: coincidencia exacta (nombre completo o nombre de camiseta)
        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            $nombreCamiseta = Str::of($jugador->nombre_camiseta ?? '')->lower()->ascii()->toString();

            if ($nombreCompleto === $normalizado || $nombreCamiseta === $normalizado) {
                return $jugador->id;
            }
        }

        // Intento 2: coincidencia parcial por apellido
        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            $apellido = Str::of($jugador->apellidos ?? '')->lower()->ascii()->toString();

            if ($apellido && (Str::contains($nombreCompleto, $normalizado) || Str::contains($normalizado, $apellido))) {
                return $jugador->id;
            }
        }

        // Intento 3: similitud de texto (para apodos/nombres cortos tipo "Mario Martín")
        $mejorId = null;
        $mejorPorcentaje = 0.0;

        foreach ($pool as $jugador) {
            $nombreCompleto = Str::of("{$jugador->nombre} {$jugador->apellidos}")->lower()->ascii()->toString();
            similar_text($normalizado, $nombreCompleto, $porcentaje);

            if ($porcentaje > $mejorPorcentaje) {
                $mejorPorcentaje = $porcentaje;
                $mejorId = $jugador->id;
            }
        }

        return $mejorPorcentaje >= 65 ? $mejorId : null;
    }
}