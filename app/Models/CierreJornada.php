<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreJornada extends Model
{
    protected $table = 'cierres_jornada';

    protected $fillable = ['id_liga', 'jornada', 'cerrada', 'cerrada_en', 'cerrada_por'];

    protected function casts(): array
    {
        return ['cerrada' => 'boolean', 'cerrada_en' => 'datetime'];
    }
}