<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TipoCartaRequest;
use App\Models\TipoCarta;
use Illuminate\Http\Request;

class TipoCartaAdminController extends Controller
{
    public function index()
    {
        $tipos = TipoCarta::with('categoria')->orderBy('id_categoria')->orderBy('rareza')->get()->map(function ($t) {
            $datos = $t->toArray();
            $datos['categoria_nombre'] = $t->categoria->nombre ?? '—';
            return $datos;
        });

        return response()->json(['data' => $tipos]);
    }

    public function show(TipoCarta $tipoCartum)
    {
        return response()->json(['data' => $tipoCartum]);
    }

    public function store(TipoCartaRequest $request)
    {
        $tipo = TipoCarta::create($request->validated());
        return response()->json(['data' => $tipo]);
    }

    public function update(TipoCartaRequest $request, TipoCarta $tipoCartum)
    {
        $tipo = $tipoCartum;
        $seDesactivaAhora = $tipo->activa && ! $request->boolean('activa');

        $tipo->update($request->validated());

        // Al desactivar una carta, cualquier copia sin jugar en manos de usuarios se recoge automáticamente.
        if ($seDesactivaAhora) {
            $tipo->cartasUsuario()->where('estado', 'en_mano')->update(['estado' => 'retirada_por_catalogo']);
        }

        return response()->json(['data' => $tipo]);
    }

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