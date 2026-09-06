<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoleadorJornada extends Model
{
    protected $table = 'goleadores_jornada';

    protected $fillable = ['id_usuario', 'id_liga', 'id_jugador', 'jornada'];

    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'id_jugador');
    }
}