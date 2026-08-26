<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JugadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'nombre_camiseta' => $this->nombre_camiseta,
            'posicion' => $this->posicion,
            'nacionalidad' => $this->nacionalidad,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
        ];
    }
}