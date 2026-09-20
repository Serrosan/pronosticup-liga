<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TipoCarta;

class CatalogoCartasController extends Controller
{
    public function index()
    {
        $tipos = TipoCarta::where('activa', true)
            ->with('categoria')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'descripcion' => $t->descripcion,
                'imagen_url' => $t->imagen_url,
                'insignia_corta' => $t->insignia_corta,
                'rareza' => $t->rareza,
                'categoria_nombre' => $t->categoria->nombre ?? '—',
                'categoria_icono' => $t->categoria->icono ?? null,
            ]);

        return response()->json(['data' => $tipos]);
    }
}