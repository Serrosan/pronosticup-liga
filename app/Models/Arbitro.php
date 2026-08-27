<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Arbitro extends Model
{
    protected $fillable = [
        'nombre', 'apellidos', 'nacionalidad', 'comunidad_autonoma',
        'anio_debut', 'promedio_tarjetas_amarillas', 'promedio_tarjetas_rojas', 'imagen',
    ];
}