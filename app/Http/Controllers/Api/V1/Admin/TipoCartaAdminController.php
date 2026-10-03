<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\CrudAdminBasico;
use App\Http\Controllers\Controller;
use App\Http\Requests\TipoCartaRequest;
use App\Models\TipoCarta;
use Illuminate\Http\Request;

class TipoCartaAdminController extends Controller
{
    use CrudAdminBasico;

    public function index()
    {
        $tipos = TipoCarta::with('categoria')->orderBy('id_categoria')->orderBy('rareza')->get()->map(function ($t) {
            $datos = $t->toArray();
            $datos['categoria_nombre'] = $t->categoria->nombre ?? '—';
            $datos['categoria_icono'] = $t->categoria->icono ?? null;
            return $datos;
        });

        return response()->json(['data' => $tipos]);
    }

    public function show(TipoCarta $tipoCartum)
    {
        return $this->mostrarRecurso($tipoCartum);
    }

    public function store(TipoCartaRequest $request)
    {
        return $this->crearRecursoPlano($request, TipoCarta::class);
    }

    // update() se queda fuera del trait: tiene un efecto secundario real
    // (retirar cartas sin jugar en manos de usuarios al desactivar el tipo).
    public function update(TipoCartaRequest $request, TipoCarta $tipoCartum)
    {
        $tipo = $tipoCartum;
        $seDesactivaAhora = $tipo->activa && ! $request->boolean('activa');

        $tipo->update($request->validated());

        if ($seDesactivaAhora) {
            $tipo->cartasUsuario()->where('estado', 'en_mano')->update(['estado' => 'retirada_por_catalogo']);
        }

        return response()->json(['data' => $tipo]);
    }

    // destroy() se queda fuera del trait: comprueba si ya se repartió alguna
    // vez antes de dejar borrar, con flujo de confirmación.
    public function destroy(Request $request, TipoCarta $tipoCartum)
    {
        $tieneHistorial = $tipoCartum->cartasUsuario()->exists();

        if ($tieneHistorial && ! $request->boolean('confirmado')) {
            return response()->json([
                'message' => 'Esta carta ya ha sido repartida a algún usuario alguna vez. Se perdería ese historial.',
                'requiere_confirmacion' => true,
            ], 409);
        }

        $tipoCartum->delete();

        return response()->json(['message' => 'Tipo de carta eliminado.']);
    }
}