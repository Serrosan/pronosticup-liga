<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaTemporada extends Model
{
    protected $table = 'plantilla_temporada';

    protected $fillable = [
        'id_jugador', 'id_equipo', 'id_temporada', 'dorsal',
        'fecha_incorporacion', 'fecha_salida',
    ];

    public function jugador()
    {
        return $this->belongsTo(Jugador::class, 'id_jugador');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }
}