<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroActividad extends Model
{
    public $timestamps = false;

    protected $table = 'registro_actividad';

    protected $fillable = ['id_usuario', 'accion', 'modelo', 'id_registro', 'cambios', 'creado_en'];

    protected $attributes = [
        'creado_en' => null,
    ];

    protected function casts(): array
    {
        return ['cambios' => 'array', 'creado_en' => 'datetime'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}