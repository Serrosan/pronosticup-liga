<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una entrada de la sección "Actualizaciones" (ver la migración create_actualizaciones_table). */
class Actualizacion extends Model
{
    protected $table = 'actualizaciones';

    protected $fillable = ['slug', 'tipo', 'fecha', 'emoji', 'titulo', 'intro', 'entra_en_vigor', 'puntos', 'notas', 'publicada'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'puntos' => 'array',
            'notas' => 'array',
            'publicada' => 'boolean',
        ];
    }
}
