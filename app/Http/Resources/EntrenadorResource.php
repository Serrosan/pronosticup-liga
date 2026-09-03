<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntrenadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nacionalidad' => $this->nacionalidad,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'foto_url' => $this->foto_url,
            'id_equipo_actual' => $this->id_equipo_actual,
            'equipo_actual' => $this->whenLoaded('equipoActual', fn () => $this->equipoActual?->nombre_corto ?? $this->equipoActual?->nombre),
        ];
    }
}