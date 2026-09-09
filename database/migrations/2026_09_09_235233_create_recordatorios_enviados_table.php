<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorios_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('users');
            $table->foreignId('id_liga')->constrained('ligas');
            $table->unsignedTinyInteger('jornada');
            $table->enum('tipo', ['pronosticos', 'goleadores']);
            $table->unsignedTinyInteger('ventana_horas');
            $table->timestamp('enviado_en');

            $table->unique(['id_usuario', 'id_liga', 'jornada', 'tipo', 'ventana_horas'], 'recordatorio_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios_enviados');
    }
};