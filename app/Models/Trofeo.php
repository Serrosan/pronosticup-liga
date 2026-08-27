<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trofeo extends Model
{
    protected $fillable = ['nombre', 'tipo', 'ambito', 'logo', 'imagen'];
}