<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo_acceso' => $this->codigo_acceso,
            'tipo' => $this->tipo,
            'temporada' => $this->temporada?->nombre,
            'mi_rol' => $this->whenPivotLoaded('liga_usuario', fn () => $this->pivot->rol),
            'total_miembros' => $this->whenCounted('usuarios'),
            'creada_en' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}