<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Actualizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin → Actualizaciones: crear, editar, publicar y borrar entradas. */
class ActualizacionesAdminController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Actualizacion::orderByRaw("CASE WHEN tipo = 'proximamente' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha')->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['slug'] = $this->slugUnico($datos['fecha'].'-'.$datos['titulo']);

        return response()->json(['data' => Actualizacion::create($datos), 'message' => 'Entrada creada.'], 201);
    }

    public function update(Request $request, Actualizacion $actualizacion)
    {
        $actualizacion->update($this->validar($request));

        return response()->json(['data' => $actualizacion->fresh(), 'message' => 'Guardado.']);
    }

    public function destroy(Actualizacion $actualizacion)
    {
        $actualizacion->delete();

        return response()->json(['message' => 'Entrada borrada.']);
    }

    private function validar(Request $request): array
    {
        $esProximamente = $request->input('tipo') === 'proximamente';

        $reglas = [
            'tipo' => ['required', 'in:proximamente,hecho'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'titulo' => ['required', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'entra_en_vigor' => ['nullable', 'string', 'max:120'],
            'puntos' => ['required', 'array', 'min:1', 'max:40'],
            'notas' => ['nullable', 'array', 'max:10'],
            'notas.*' => ['nullable', 'string', 'max:500'],
            'publicada' => ['required', 'boolean'],
        ];

        if ($esProximamente) {
            $reglas += [
                'puntos.*' => ['array'],
                'puntos.*.carta' => ['required', 'string', 'max:120'],
                'puntos.*.antes' => ['nullable', 'string', 'max:500'],
                'puntos.*.despues' => ['nullable', 'string', 'max:500'],
                'puntos.*.porque' => ['nullable', 'string', 'max:500'],
            ];
        } else {
            $reglas['puntos.*'] = ['required', 'string', 'max:500'];
        }

        $datos = $request->validate($reglas, [
            'puntos.required' => 'Pon al menos un punto.',
            'puntos.min' => 'Pon al menos un punto.',
            'puntos.*.carta.required' => 'Cada cambio necesita su título (qué cambia).',
            'puntos.*.required' => 'Hay un punto vacío: rellénalo o quítalo.',
        ]);

        // Solo se guardan los campos de cada fila que tocan, sin basura.
        if ($esProximamente) {
            $datos['puntos'] = collect($datos['puntos'])->map(fn ($p) => [
                'carta' => $p['carta'],
                'antes' => $p['antes'] ?? null,
                'despues' => $p['despues'] ?? null,
                'porque' => $p['porque'] ?? null,
            ])->values()->all();
        } else {
            $datos['intro'] = $datos['intro'] ?? null;
            $datos['entra_en_vigor'] = null;
        }
        $datos['notas'] = array_values(array_filter($datos['notas'] ?? [], fn ($n) => trim((string) $n) !== ''));

        return $datos;
    }

    private function slugUnico(string $base): string
    {
        $slug = Str::limit(Str::slug($base), 120, '');
        $final = $slug;
        for ($i = 2; Actualizacion::where('slug', $final)->exists(); $i++) {
            $final = "{$slug}-{$i}";
        }

        return $final;
    }
}
