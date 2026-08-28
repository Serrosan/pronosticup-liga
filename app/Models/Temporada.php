<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    use HasFactory;

    protected $fillable = ['id_competicion', 'nombre', 'fecha_inicio', 'fecha_fin'];

    public function competicion()
    {
        return $this->belongsTo(Competicion::class, 'id_competicion');
    }
}