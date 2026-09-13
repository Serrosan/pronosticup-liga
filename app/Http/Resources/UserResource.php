<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rolEnLigaActiva = null;

        if ($this->ligaActiva) {
            $rolEnLigaActiva = $this->ligaActiva->usuarios()
                ->where('id_usuario', $this->id)
                ->first()?->pivot?->rol;
        }

        return [
            'id' => $this->id,
            'nombre' => $this->nombre_visible ?? $this->name,
            'nombre_visible' => $this->nombre_visible,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url ? url($this->avatar_url) : null,
            'activado' => ! is_null($this->activado_en),
            'es_superadmin' => (bool) $this->es_superadmin,
            'recibir_email_recordatorios' => (bool) $this->recibir_email_recordatorios,
            'recibir_email_puntos' => (bool) $this->recibir_email_puntos,
            'liga_activa' => $this->ligaActiva ? [
                'id' => $this->ligaActiva->id,
                'nombre' => $this->ligaActiva->nombre,
                'codigo_acceso' => $this->ligaActiva->codigo_acceso,
                'rol' => $rolEnLigaActiva,
                'logo_url' => $this->ligaActiva->logo_url ? url($this->ligaActiva->logo_url) : null,
                'lema' => $this->ligaActiva->lema,
            ] : null,
        ];
    }
}