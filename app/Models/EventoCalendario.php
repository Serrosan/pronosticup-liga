<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventoCalendario extends Model
{
    use HasFactory;

    protected $table = 'eventos_calendario';

    protected $fillable = ['fecha_inicio', 'fecha_fin', 'titulo', 'color'];
}