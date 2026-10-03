<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadisticaPartido extends Model
{
    protected $table = 'estadisticas_partido';

    protected $fillable = [
        'id_partido', 'id_equipo', 'posesion', 'remates', 'efectividad',
        'faltas', 'tarjetas_amarillas', 'tarjetas_rojas', 'fueras_de_juego',
        'corners', 'penaltis_marcados', 'penaltis_intentados',
    ];

    public function partido(): BelongsTo
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }
}