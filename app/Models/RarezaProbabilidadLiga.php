<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RarezaProbabilidadLiga extends Model
{
    protected $table = 'rarezas_probabilidad_liga';

    protected $fillable = ['id_liga', 'id_categoria', 'rareza', 'porcentaje'];

    public function liga()
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaCarta::class, 'id_categoria');
    }
}