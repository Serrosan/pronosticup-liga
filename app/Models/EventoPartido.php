<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoPartido extends Model
{
    protected $table = 'eventos_partido';

    protected $fillable = [
        'id_partido', 'id_jugador', 'id_equipo', 'minuto',
        'tipo_evento', 'id_jugador_relacionado',
    ];

    public function partido()
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }

    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'id_jugador');
    }
}