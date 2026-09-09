<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordatorioEnviado extends Model
{
    protected $table = 'recordatorios_enviados';

    protected $fillable = [
        'id_usuario', 'id_liga', 'jornada', 'tipo', 'ventana_horas', 'enviado_en',
    ];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }
}