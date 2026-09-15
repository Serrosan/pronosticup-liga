<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaCarta extends Model
{
    protected $table = 'categorias_carta';

    protected $fillable = ['nombre', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function tiposCarta()
    {
        return $this->hasMany(TipoCarta::class, 'id_categoria');
    }

    public function configuracionesLiga()
    {
        return $this->hasMany(ConfiguracionCartasLiga::class, 'id_categoria');
    }

    public function rarezasProbabilidad()
    {
        return $this->hasMany(RarezaProbabilidadLiga::class, 'id_categoria');
    }
}