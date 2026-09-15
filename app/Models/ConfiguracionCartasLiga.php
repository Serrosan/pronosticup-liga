<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionCartasLiga extends Model
{
    protected $table = 'configuracion_cartas_liga';

    protected $fillable = ['id_liga', 'id_categoria', 'cantidad_reparto_semanal'];

    public function liga()
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaCarta::class, 'id_categoria');
    }
}