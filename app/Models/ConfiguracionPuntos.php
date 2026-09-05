<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPuntos extends Model
{
    protected $table = 'configuracion_puntos';

    protected $fillable = [
        'id_liga', 'puntos_signo', 'puntos_diferencia', 'puntos_exacto',
        'bonus_pleno_7', 'bonus_pleno_8', 'bonus_pleno_9', 'bonus_pleno_10',
        'puntos_gol_goleador',
    ];

    public static function paraLiga(int $idLiga): self
    {
        return static::where('id_liga', $idLiga)->first()
            ?? static::where('id_liga', null)->first()
            ?? static::create(['id_liga' => null]);
    }
}