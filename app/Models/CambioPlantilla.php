<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioPlantilla extends Model
{
    protected $table = 'cambios_plantilla';

    protected $fillable = [
        'tipo', 'estado', 'pista', 'id_equipo', 'id_temporada', 'clave',
        'nombre_laliga', 'apodo_laliga', 'nombre_pila_laliga', 'apellidos_laliga',
        'dorsal', 'foto_laliga', 'id_jugador_sugerido', 'motivo',
        'partidos', 'partidos_por_reimportar',
        'id_jugador_resuelto', 'resuelto_como', 'resuelto_en',
    ];

    protected $casts = [
        'id_equipo' => 'integer',
        'id_temporada' => 'integer',
        'dorsal' => 'integer',
        'id_jugador_sugerido' => 'integer',
        'id_jugador_resuelto' => 'integer',
        'partidos' => 'array',
        'partidos_por_reimportar' => 'array',
        'resuelto_en' => 'datetime',
    ];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }

    public function sugerido(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'id_jugador_sugerido');
    }
}
