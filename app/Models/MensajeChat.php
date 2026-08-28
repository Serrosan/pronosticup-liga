<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensajeChat extends Model
{
    use HasFactory;

    protected $table = 'mensajes_chat';

    protected $fillable = ['id_liga', 'id_usuario', 'texto', 'tipo', 'adjunto_url', 'reacciones'];

    protected function casts(): array
    {
        return ['reacciones' => 'array'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}