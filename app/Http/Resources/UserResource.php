<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre_visible ?? $this->name,
            'nombre_visible' => $this->nombre_visible,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url ? url($this->avatar_url) : null,
            'activado' => ! is_null($this->activado_en),
            'es_superadmin' => (bool) $this->es_superadmin,
            'liga_activa' => $this->ligaActiva ? [
                'id' => $this->ligaActiva->id,
                'nombre' => $this->ligaActiva->nombre,
                'codigo_acceso' => $this->ligaActiva->codigo_acceso,
            ] : null,
        ];
    }
}