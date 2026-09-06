<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuinielaPosiciones extends Model
{
    protected $table = 'quinielas_posiciones';

    protected $fillable = ['id_liga', 'tipo', 'abierta', 'resuelta', 'resuelta_en'];

    protected function casts(): array
    {
        return ['abierta' => 'boolean', 'resuelta' => 'boolean', 'resuelta_en' => 'datetime'];
    }

    public function predicciones()
    {
        return $this->hasMany(PrediccionQuiniela::class, 'id_quiniela');
    }
}