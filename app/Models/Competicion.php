<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competicion extends Model
{
    use HasFactory;

    protected $table = 'competiciones';

    protected $fillable = ['nombre', 'pais', 'codigo_externo'];

    public function temporadas()
    {
        return $this->hasMany(Temporada::class);
    }
}