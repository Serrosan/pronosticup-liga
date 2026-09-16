<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Liga extends Model
{
    use HasFactory;

    protected $table = 'ligas';

    protected $fillable = [
        'nombre', 'codigo_acceso', 'id_temporada', 'id_usuario_creador', 'logo_url', 'lema', 'tipo', 'tope_mano_cartas',
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

    public function configuracionesCartas()
    {
        return $this->hasMany(ConfiguracionCartasLiga::class, 'id_liga');
    }

    public function rarezasProbabilidadCartas()
    {
        return $this->hasMany(RarezaProbabilidadLiga::class, 'id_liga');
    }

    public function bonusTop3ProbabilidadCartas()
    {
        return $this->hasMany(BonusTop3ProbabilidadLiga::class, 'id_liga');
    }
}