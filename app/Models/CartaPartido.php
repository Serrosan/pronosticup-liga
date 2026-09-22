<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartaPartido extends Model
{
    protected $table = 'carta_partidos';

    protected $fillable = ['id_carta_usuario', 'id_partido'];

    public function cartaUsuario()
    {
        return $this->belongsTo(CartaUsuario::class, 'id_carta_usuario');
    }

    public function partido()
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }
}