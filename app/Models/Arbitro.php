<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Arbitro extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'apellidos', 'nacionalidad', 'comunidad_autonoma',
        'anio_debut', 'promedio_tarjetas_amarillas', 'promedio_tarjetas_rojas', 'imagen',
    ];
}