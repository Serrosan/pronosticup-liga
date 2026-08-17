<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_acceso')->unique();
            $table->string('tipo')->default('Normal'); // Normal / ConExtras
            $table->foreignId('id_temporada')->constrained('temporadas');
            $table->foreignId('id_usuario_creador')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligas');
    }
};