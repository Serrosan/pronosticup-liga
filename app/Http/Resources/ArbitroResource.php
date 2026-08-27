<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArbitroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'nacionalidad' => $this->nacionalidad,
            'comunidad_autonoma' => $this->comunidad_autonoma,
            'anio_debut' => $this->anio_debut,
            'promedio_tarjetas_amarillas' => $this->promedio_tarjetas_amarillas,
            'promedio_tarjetas_rojas' => $this->promedio_tarjetas_rojas,
            'imagen' => $this->imagen,
        ];
    }
}