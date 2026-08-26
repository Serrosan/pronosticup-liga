<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jornada' => $this->jornada,
            'equipo_local' => new EquipoResource($this->whenLoaded('equipoLocal')),
            'equipo_visitante' => new EquipoResource($this->whenLoaded('equipoVisitante')),
            'horario_estimado' => $this->horario_estimado?->format('Y-m-d H:i'),
            'horario_oficial' => $this->horario_oficial?->format('Y-m-d H:i'),
            'goles_casa' => $this->goles_casa,
            'goles_fuera' => $this->goles_fuera,
            'estado' => $this->estado,
        ];
    }
}