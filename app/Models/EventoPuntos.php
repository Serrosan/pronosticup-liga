<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoPuntos extends Model
{
    protected $table = 'eventos_puntos';

    protected $fillable = ['id_usuario', 'id_liga', 'id_partido', 'jornada', 'tipo_evento', 'puntos'];
}