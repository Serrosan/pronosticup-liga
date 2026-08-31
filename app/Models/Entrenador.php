<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrenador extends Model
{
    use HasFactory;

    protected $table = 'entrenadores';

    protected $fillable = ['nombre', 'nacionalidad', 'fecha_nacimiento', 'id_equipo_actual'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    public function equipoActual()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_actual');
    }
}