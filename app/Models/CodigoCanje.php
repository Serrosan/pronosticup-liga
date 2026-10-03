<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodigoCanje extends Model
{
    protected $table = 'codigos_canje';

    protected $fillable = ['codigo', 'nombre', 'tipo_premio', 'id_tipo_carta', 'usos_maximos', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function tipoCarta(): BelongsTo
    {
        return $this->belongsTo(TipoCarta::class, 'id_tipo_carta');
    }

    public function canjes(): HasMany
    {
        return $this->hasMany(CanjeCodigo::class, 'id_codigo');
    }
}