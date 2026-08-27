<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nombre_corto' => $this->nombre_corto,
            'siglas' => $this->siglas,
            'ciudad' => $this->ciudad,
            'escudo_url' => $this->escudo_url,
            'color_primario' => $this->color_primario,
            'color_secundario' => $this->color_secundario,
        ];
    }
}