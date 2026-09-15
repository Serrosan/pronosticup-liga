<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaCartaRequest;
use App\Models\CategoriaCarta;
use Illuminate\Http\Request;

class CategoriaCartaAdminController extends Controller
{
    public function index()
    {
        return response()->json(['data' => CategoriaCarta::withCount('tiposCarta')->orderBy('nombre')->get()]);
    }

    public function show(CategoriaCarta $categoriaCarta)
    {
        return response()->json(['data' => $categoriaCarta]);
    }

    public function store(CategoriaCartaRequest $request)
    {
        $categoria = CategoriaCarta::create($request->validated());
        return response()->json(['data' => $categoria]);
    }

    public function update(CategoriaCartaRequest $request, CategoriaCarta $categoriaCarta)
    {
        $categoriaCarta->update($request->validated());
        return response()->json(['data' => $categoriaCarta]);
    }

    public function destroy(Request $request, CategoriaCarta $categoriaCarta)
    {
        $tieneTipos = $categoriaCarta->tiposCarta()->exists();

        if ($tieneTipos && ! $request->boolean('confirmado')) {
            return response()->json([
                'message' => 'Esta categoría tiene tipos de carta asociados. Se eliminarían también.',
                'requiere_confirmacion' => true,
            ], 409);
        }

        $categoriaCarta->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}