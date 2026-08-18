<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarioPartido extends Model
{
    protected $table = 'calendariopartidos';

    protected $fillable = [
        'id_temporada', 'id_equipo_local', 'id_equipo_visitante', 'id_estadio',
        'jornada', 'horario_estimado', 'horario_oficial', 'id_arbitro',
        'goles_casa', 'goles_fuera', 'estado', 'asistencia', 'id_externo_api',
    ];

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_local');
    }

    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_visitante');
    }
}