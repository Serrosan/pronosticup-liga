<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estadio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'ciudad', 'capacidad', 'tamanio_campo',
        'anio_construccion', 'anio_ult_remodelacion', 'foto_url',
    ];

    public function equipo()
    {
        return $this->hasOne(Equipo::class, 'id_estadio');
    }
}