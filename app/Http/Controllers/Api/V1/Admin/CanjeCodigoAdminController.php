<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanjeCodigo;

class CanjeCodigoAdminController extends Controller
{
    /**
     * Solo lectura a propósito — no es un recurso editable como el resto, es un
     * registro histórico de quién canjeó qué código y cuándo.
     */
    public function index()
    {
        $canjes = CanjeCodigo::with(['codigo', 'usuario'])
            ->orderByDesc('canjeado_en')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'codigo' => $c->codigo->codigo,
                'nombre_codigo' => $c->codigo->nombre,
                'usuario' => $c->usuario->nombre_visible ?? $c->usuario->name,
                'canjeado_en' => $c->canjeado_en->format('d/m/Y H:i'),
            ]);

        return response()->json(['data' => $canjes]);
    }
}