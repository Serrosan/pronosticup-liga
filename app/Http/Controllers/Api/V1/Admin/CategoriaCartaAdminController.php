<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaCartaRequest;
use App\Models\CategoriaCarta;
use Illuminate\Http\Request;

class CategoriaCartaAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        return response()->json(['data' => CategoriaCarta::withCount('tiposCarta')->orderBy('nombre')->get()]);
    }

    public function show(CategoriaCarta $categoriaCarta)
    {
        return $this->mostrarRecurso($categoriaCarta);
    }

    public function store(CategoriaCartaRequest $request)
    {
        return $this->crearRecursoPlano($request, CategoriaCarta::class);
    }

    public function update(CategoriaCartaRequest $request, CategoriaCarta $categoriaCarta)
    {
        return $this->actualizarRecursoPlano($request, $categoriaCarta);
    }

    // destroy() se queda fuera del trait: necesita comprobar si tiene tipos
    // de carta asociados antes de dejar borrar, con flujo de confirmación.
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