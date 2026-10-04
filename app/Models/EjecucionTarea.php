<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjecucionTarea extends Model
{
    protected $table = 'ejecuciones_tarea';

    protected $fillable = [
        'tarea', 'origen', 'estado', 'id_usuario', 'lanzada_por',
        'parametros', 'salida', 'iniciada_en', 'terminada_en',
    ];

    protected $casts = [
        'parametros' => 'array',
        'iniciada_en' => 'datetime',
        'terminada_en' => 'datetime',
    ];
}
