<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jugadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_completo');
            $table->string('nombre_visible');
            $table->string('posicion'); // Portero / Defensa / Centrocampista / Delantero (PHP Enum, no enum de BD)
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('altura')->nullable();
            $table->string('pie')->nullable();
            $table->string('foto_url')->nullable();
            $table->string('id_externo_api')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jugadores');
    }
};