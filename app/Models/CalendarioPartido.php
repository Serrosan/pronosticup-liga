<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarioPartido extends Model
{
    use HasFactory;

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

    public function estadio()
    {
        return $this->belongsTo(Estadio::class, 'id_estadio');
    }

    public function arbitro()
    {
        return $this->belongsTo(Arbitro::class, 'id_arbitro');
    }
    
    protected function casts(): array
    {
        return [
            'horario_estimado' => 'datetime',
            'horario_oficial' => 'datetime',
        ];
    }
}