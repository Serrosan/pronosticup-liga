<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CodigoCanjeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo_premio' => $this->tipo_premio,
            'id_tipo_carta' => $this->id_tipo_carta,
            'carta' => $this->tipoCarta?->nombre,
            'usos_maximos' => $this->usos_maximos,
            // Requiere ->withCount('canjes') en la consulta que arma el listado —
            // así se evita 1 consulta extra por fila (N+1).
            'total_canjes' => $this->canjes_count ?? $this->canjes()->count(),
            'activo' => (bool) $this->activo,
        ];
    }
}