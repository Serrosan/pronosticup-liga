<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Liga extends Model
{
    use HasFactory;

    protected $table = 'ligas';

    protected $fillable = [
        'nombre', 'codigo_acceso', 'id_temporada', 'id_usuario_creador', 'logo_url', 'lema',
    ];

    public function temporada()
    {
        return $this->belongsTo(Temporada::class, 'id_temporada');
    }

    public function usuarioCreador()
    {
        return $this->belongsTo(User::class, 'id_usuario_creador');
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'liga_usuario', 'id_liga', 'id_usuario')
            ->withPivot('rol')
            ->withTimestamps();
    }
}