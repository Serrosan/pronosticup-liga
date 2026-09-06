<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrediccionQuiniela extends Model
{
    protected $table = 'predicciones_quiniela';

    protected $fillable = ['id_quiniela', 'id_usuario', 'id_equipo', 'posicion_predicha', 'puntos_obtenidos'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo');
    }
}