<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CambioPlantilla;
use App\Models\Jugador;
use App\Services\CambiosPlantillaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /admin/cambios-plantilla — la cola de lo que LaLiga dice y la base de datos
 * no: jugadores que el scraper no ha podido emparejar y dorsales distintos.
 * Cada cambio se revisa y se resuelve con un clic; nada se aplica solo.
 */
class CambiosPlantillaAdminController extends Controller
{
    public function __construct(private CambiosPlantillaService $cambios) {}

    public function index()
    {
        $pendientes = CambioPlantilla::with(['equipo', 'sugerido'])
            ->where('estado', 'pendiente')
            ->get()
            ->sortBy(fn ($c) => [$c->equipo->nombre_corto ?? $c->equipo->nombre ?? '', $c->dorsal ?? 999, $c->nombre_laliga])
            ->values()
            ->map(fn ($c) => $this->fila($c));

        $porActualizar = CambioPlantilla::where('estado', 'resuelto')
            ->whereNotNull('partidos_por_reimportar')
            ->get()
            ->flatMap(fn ($c) => $c->partidos_por_reimportar ?? [])
            ->unique()
            ->count();

        return response()->json(['data' => [
            'pendientes' => $pendientes,
            'partidos_por_actualizar' => $porActualizar,
            'ignorados' => CambioPlantilla::where('estado', 'ignorado')->count(),
            'posiciones' => CambiosPlantillaService::POSICIONES,
            'dorsal_canterano' => CambiosPlantillaService::DORSAL_CANTERANO,
        ]]);
    }

    /** Solo el número, para el aviso del menú del admin. */
    public function resumen()
    {
        return response()->json(['data' => ['pendientes' => CambioPlantilla::where('estado', 'pendiente')->count()]]);
    }

    public function candidatos(Request $request, CambioPlantilla $cambio)
    {
        return response()->json(['data' => $this->cambios->candidatos($cambio, $request->query('q'))]);
    }

    public function alta(Request $request, CambioPlantilla $cambio)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['nullable', 'string', 'max:255'],
            'nombre_camiseta' => ['nullable', 'string', 'max:255'],
            'posicion' => ['required', Rule::in(CambiosPlantillaService::POSICIONES)],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
            'pie' => ['nullable', 'string', 'max:50'],
            'nacionalidad' => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:255'],
            'altura' => ['nullable', 'integer'],
            'fecha_fin_contrato' => ['nullable', 'date'],
            'club_anterior' => ['nullable', 'string', 'max:255'],
            'fecha_incorporacion' => ['nullable', 'date'],
            'usar_foto_laliga' => ['nullable', 'boolean'],
        ]);

        return $this->resolver(function () use ($cambio, $datos) {
            $resultado = $this->cambios->darDeAlta($cambio, $datos);

            return [
                'message' => $resultado['aviso'] ?? 'Jugador dado de alta.',
                'data' => ['id_jugador' => $resultado['jugador']->id],
            ];
        });
    }

    public function asignar(Request $request, CambioPlantilla $cambio)
    {
        $datos = $request->validate([
            'id_jugador' => ['required', 'integer', 'exists:jugadores,id'],
            'actualizar_dorsal' => ['nullable', 'boolean'],
        ]);

        return $this->resolver(function () use ($cambio, $datos) {
            $nota = $this->cambios->asignar($cambio, Jugador::findOrFail($datos['id_jugador']), (bool) ($datos['actualizar_dorsal'] ?? false));

            return ['message' => 'Asignado. A partir de ahora ese nombre de LaLiga se empareja con él.'.($nota ? ' '.$nota : '')];
        });
    }

    public function dorsal(CambioPlantilla $cambio)
    {
        return $this->resolver(function () use ($cambio) {
            $this->cambios->aplicarDorsal($cambio);

            return ['message' => 'Dorsal actualizado.'];
        });
    }

    /** Aplica de una vez todos los dorsales pendientes que no choquen con otro jugador. */
    public function dorsales()
    {
        $aplicados = 0;
        $conflictos = [];

        foreach (CambioPlantilla::where('tipo', 'dorsal')->where('estado', 'pendiente')->orderBy('id')->get() as $cambio) {
            try {
                $this->cambios->aplicarDorsal($cambio);
                $aplicados++;
            } catch (\DomainException $e) {
                $conflictos[] = "{$cambio->nombre_laliga}: {$e->getMessage()}";
            }
        }

        $mensaje = "{$aplicados} dorsal(es) actualizados.";
        if ($conflictos) {
            $mensaje .= ' '.count($conflictos).' se quedan pendientes porque chocan con otro jugador.';
        }

        return response()->json(['message' => $mensaje, 'data' => ['aplicados' => $aplicados, 'conflictos' => $conflictos]]);
    }

    public function ignorar(CambioPlantilla $cambio)
    {
        $this->cambios->ignorar($cambio);

        return response()->json(['message' => 'Ignorado. No volverá a salir en los avisos.']);
    }

    public function arreglado(CambioPlantilla $cambio)
    {
        return $this->resolver(function () use ($cambio) {
            $this->cambios->darPorArreglado($cambio);

            return ['message' => 'Marcado como arreglado.'];
        });
    }

    /** Los errores que el admin debe leer (dorsal ocupado, ya no está pendiente...) salen como 422 con su mensaje. */
    private function resolver(callable $accion)
    {
        try {
            return response()->json($accion());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function fila(CambioPlantilla $c): array
    {
        return [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'pista' => $c->pista,
            'equipo' => [
                'id' => $c->id_equipo,
                'nombre' => $c->equipo->nombre_corto ?? $c->equipo->nombre ?? '?',
                'escudo_url' => $c->equipo->escudo_url ?? null,
            ],
            'nombre_laliga' => $c->nombre_laliga,
            'apodo_laliga' => $c->apodo_laliga,
            'nombre_pila_laliga' => $c->nombre_pila_laliga,
            'apellidos_laliga' => $c->apellidos_laliga,
            'dorsal' => $c->dorsal,
            'foto_laliga' => $c->foto_laliga,
            'veces_visto' => count($c->partidos ?? []),
            'motivo' => $c->motivo,
            'es_canterano' => $c->dorsal !== null && $c->dorsal >= CambiosPlantillaService::DORSAL_CANTERANO,
            'sugerido' => $c->sugerido ? [
                'id' => $c->sugerido->id,
                'nombre' => trim("{$c->sugerido->nombre} {$c->sugerido->apellidos}"),
            ] : null,
        ];
    }
}
