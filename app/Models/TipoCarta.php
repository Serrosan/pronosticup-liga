<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCarta extends Model
{
    protected $table = 'tipos_carta';

    protected $fillable = ['id_categoria', 'rareza', 'nombre', 'descripcion', 'imagen_url', 'insignia_corta', 'codigo_efecto', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaCarta::class, 'id_categoria');
    }

    public function cartasUsuario()
    {
        return $this->hasMany(CartaUsuario::class, 'id_tipo_carta');
    }
}