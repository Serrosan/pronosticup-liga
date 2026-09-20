<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartaUsuario extends Model
{
    protected $table = 'cartas_usuario';

    protected $fillable = [
        'id_usuario', 'id_liga', 'id_tipo_carta', 'jornada_obtenida', 'obtenida_en', 'revelada_en',
        'origen', 'estado', 'id_partido', 'id_usuario_objetivo', 'jornada_efecto',
        'jugada_en', 'puntos_generados',
    ];

    protected function casts(): array
    {
        return ['obtenida_en' => 'datetime', 'revelada_en' => 'datetime', 'jugada_en' => 'datetime'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function usuarioObjetivo()
    {
        return $this->belongsTo(User::class, 'id_usuario_objetivo');
    }

    public function liga()
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }

    public function tipoCarta()
    {
        return $this->belongsTo(TipoCarta::class, 'id_tipo_carta');
    }

    public function partido()
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }
}