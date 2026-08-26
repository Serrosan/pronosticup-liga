<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PronosticoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_partido' => $this->id_partido,
            'resultado_1x2' => $this->resultado_1x2,
            'goles_local_predicho' => $this->goles_local_predicho,
            'goles_visitante_predicho' => $this->goles_visitante_predicho,
            'enviado_en' => $this->enviado_en?->format('Y-m-d H:i'),
            'editado_en' => $this->editado_en?->format('Y-m-d H:i'),
        ];
    }
}