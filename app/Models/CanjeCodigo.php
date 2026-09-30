<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanjeCodigo extends Model
{
    protected $table = 'canjes_codigo';

    protected $fillable = ['id_codigo', 'id_usuario', 'id_liga', 'canjeado_en'];

    protected $casts = ['canjeado_en' => 'datetime'];

    public function codigo(): BelongsTo
    {
        return $this->belongsTo(CodigoCanje::class, 'id_codigo');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function liga(): BelongsTo
    {
        return $this->belongsTo(Liga::class, 'id_liga');
    }
}