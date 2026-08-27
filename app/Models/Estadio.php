<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estadio extends Model
{
    protected $fillable = [
        'nombre', 'ciudad', 'capacidad', 'tamanio_campo',
        'anio_construccion', 'anio_ult_remodelacion',
    ];
}