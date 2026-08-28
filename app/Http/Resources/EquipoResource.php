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
            'apodo' => $this->apodo,
            'siglas' => $this->siglas,
            'ciudad' => $this->ciudad,
            'id_estadio' => $this->id_estadio,
            'año_fundacion' => $this->año_fundacion,
            'escudo_url' => $this->escudo_url,
            'color_primario' => $this->color_primario,
            'color_secundario' => $this->color_secundario,
            'num_socios' => $this->num_socios,
            'num_abonados' => $this->num_abonados,
            'camiseta_1' => $this->camiseta_1,
            'camiseta_2' => $this->camiseta_2,
            'camiseta_3' => $this->camiseta_3,
            'camiseta_1_reverso' => $this->camiseta_1_reverso,
            'camiseta_2_reverso' => $this->camiseta_2_reverso,
            'camiseta_3_reverso' => $this->camiseta_3_reverso,
            'id_externo_api' => $this->id_externo_api,
            'id_equipo_api' => $this->id_equipo_api,
        ];
    }
}