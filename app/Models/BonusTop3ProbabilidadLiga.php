<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusTop3ProbabilidadLiga extends Model
{
    protected $table = 'bonus_top3_probabilidad_liga';

    protected $fillable = ['id_liga', 'posicion', 'rareza', 'porcentaje'];

    public function liga()
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }
}