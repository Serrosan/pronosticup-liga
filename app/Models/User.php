<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Liga;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'nombre_visible', 'avatar_url','liga_activa_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activado_en' => 'datetime',
            'password' => 'hashed',
            'es_superadmin' => 'boolean',
        ];
    }

    public function ligas()
    {
        return $this->belongsToMany(Liga::class, 'liga_usuario', 'id_usuario', 'id_liga')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function ligaActiva()
    {
        return $this->belongsTo(Liga::class, 'liga_activa_id');
    }
}