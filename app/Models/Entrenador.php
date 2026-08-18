<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entrenador extends Model
{
    protected $table = 'entrenadores';

    protected $fillable = ['nombre', 'nacionalidad', 'fecha_nacimiento', 'id_equipo_actual'];

    public function equipoActual()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_actual');
    }
}