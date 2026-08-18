<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PalmaresEquipoTrofeo extends Model
{
    protected $table = 'palmares_equipo_trofeo';

    protected $fillable = ['id_equipo', 'id_trofeo', 'año'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }

    public function trofeo()
    {
        return $this->belongsTo(Trofeo::class, 'id_trofeo');
    }
}