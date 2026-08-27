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
            'posicion_detallada' => $this->posicion_detallada,
            'pie' => $this->pie,
            'nacionalidad' => $this->nacionalidad,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'lugar_nacimiento' => $this->lugar_nacimiento,
            'seleccion' => $this->seleccion,
            'altura' => $this->altura,
            'fecha_fin_contrato' => $this->fecha_fin_contrato,
            'club_anterior' => $this->club_anterior,
            'id_externo_api' => $this->id_externo_api,
            'foto_url' => $this->foto_url,
        ];
    }
}