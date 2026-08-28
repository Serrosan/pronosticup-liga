<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'nombre_corto', 'apodo', 'siglas', 'ciudad', 'id_estadio',
        'año_fundacion', 'escudo_url', 'color_primario', 'color_secundario',
        'num_socios', 'num_abonados', 'camiseta_1', 'camiseta_2', 'camiseta_3',
        'camiseta_1_reverso', 'camiseta_2_reverso', 'camiseta_3_reverso',
        'id_externo_api', 'id_equipo_api',
    ];

    public function estadio()
    {
        return $this->belongsTo(Estadio::class, 'id_estadio');
    }
}