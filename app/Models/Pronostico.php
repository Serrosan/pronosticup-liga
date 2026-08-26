<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pronostico extends Model
{
    protected $table = 'pronosticos';

    protected $fillable = [
        'id_usuario', 'id_liga', 'id_partido',
        'resultado_1x2', 'goles_local_predicho', 'goles_visitante_predicho',
        'enviado_en', 'editado_en',
    ];

    protected function casts(): array
    {
        return [
            'enviado_en' => 'datetime',
            'editado_en' => 'datetime',
        ];
    }

    public function partido()
    {
        return $this->belongsTo(CalendarioPartido::class, 'id_partido');
    }
}