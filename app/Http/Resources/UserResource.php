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
            'nombre' => $this->name,
            'email' => $this->email,
            'nombre_visible' => $this->nombre_visible,
            'avatar_url' => $this->avatar_url,
            'activado' => ! is_null($this->activado_en),
            'es_superadmin' => $this->es_superadmin,
        ];
    }
}