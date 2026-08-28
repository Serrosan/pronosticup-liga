<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trofeo extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'tipo', 'ambito', 'logo', 'imagen'];
}