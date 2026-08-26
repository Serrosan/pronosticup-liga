<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstadioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'ciudad' => $this->ciudad,
            'capacidad' => $this->capacidad,
            'tamanio_campo' => $this->tamanio_campo,
        ];
    }
}