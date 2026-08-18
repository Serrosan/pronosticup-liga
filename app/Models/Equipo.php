<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $fillable = [
        'nombre', 'nombre_corto', 'apodo', 'ciudad', 'id_estadio',
        'año_fundacion', 'escudo_url', 'color_primario', 'color_secundario', 'id_externo_api',
    ];

    public function estadio()
    {
        return $this->belongsTo(Estadio::class, 'id_estadio');
    }
}