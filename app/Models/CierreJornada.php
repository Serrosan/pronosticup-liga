<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreJornada extends Model
{
    protected $table = 'cierres_jornada';

    protected $fillable = ['id_liga', 'jornada', 'cerrada', 'cerrada_en', 'cerrada_por', 'goleadores_calculados_en', 'puntos_notificados_en'];

    protected function casts(): array
    {
        return ['cerrada' => 'boolean', 'cerrada_en' => 'datetime', 'goleadores_calculados_en' => 'datetime', 'puntos_notificados_en' => 'datetime'];
    }
}