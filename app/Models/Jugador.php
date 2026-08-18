<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jugador extends Model
{
    protected $table = 'jugadores';

    protected $fillable = [
        'nombre', 'apellidos', 'posicion', 'fecha_nacimiento', 'lugar_nacimiento',
        'nacionalidad', 'seleccion', 'altura', 'pie', 'foto_url',
        'fecha_fin_contrato', 'club_anterior', 'id_externo_api',
    ];
}