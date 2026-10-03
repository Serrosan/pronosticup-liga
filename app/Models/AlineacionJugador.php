<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlineacionJugador extends Model
{
    protected $table = 'alineaciones_jugador';

    protected $fillable = ['id_partido', 'id_equipo', 'id_jugador', 'dorsal', 'titular', 'posicion_formacion', 'formacion'];

    protected $casts = ['titular' => 'boolean'];

    public function partido(): BelongsTo
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'id_jugador');
    }
}