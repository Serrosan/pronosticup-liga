<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliasJugadorLaliga extends Model
{
    protected $table = 'alias_jugador_laliga';

    protected $fillable = ['id_equipo', 'clave', 'id_jugador'];
}
