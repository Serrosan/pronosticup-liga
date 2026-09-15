<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Novedad extends Model
{
    use HasFactory;

    protected $table = 'novedades';

    protected $fillable = ['id_liga', 'titulo', 'emoji', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function liga()
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }
}