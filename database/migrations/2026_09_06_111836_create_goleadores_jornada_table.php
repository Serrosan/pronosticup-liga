<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goleadores_jornada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->foreignId('id_jugador')->constrained('jugadores')->cascadeOnDelete();
            $table->unsignedInteger('jornada');
            $table->timestamps();

            $table->unique(['id_usuario', 'id_liga', 'jornada', 'id_jugador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goleadores_jornada');
    }
};