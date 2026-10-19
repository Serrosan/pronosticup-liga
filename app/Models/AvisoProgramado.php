<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvisoProgramado extends Model
{
    protected $table = 'avisos_programados';

    protected $fillable = ['id_admin', 'para', 'titulo', 'mensaje', 'url', 'enviar_en', 'enviado_en', 'personas'];

    protected function casts(): array
    {
        return ['enviar_en' => 'datetime', 'enviado_en' => 'datetime'];
    }
}
