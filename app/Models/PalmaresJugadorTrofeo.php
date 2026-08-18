<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalmaresJugadorTrofeo extends Model
{
    protected $table = 'palmares_jugadores_trofeos';

    protected $fillable = ['id_jugador', 'id_trofeo', 'id_club', 'año'];

    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'id_jugador');
    }

    public function trofeo()
    {
        return $this->belongsTo(Trofeo::class, 'id_trofeo');
    }

    public function club()
    {
        return $this->belongsTo(Equipo::class, 'id_club');
    }
}