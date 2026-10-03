<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_canje', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            // Solo para ti, en el admin — para acordarte de qué era cada uno sin
            // tener que recordar el texto exacto del código ("Cumpleaños Sergio 2026").
            $table->string('nombre');
            $table->enum('tipo_premio', ['tirada_aleatoria', 'carta_especifica']);
            // Solo se usa si tipo_premio = carta_especifica; null si es tirada_aleatoria.
            $table->foreignId('id_tipo_carta')->nullable()->constrained('tipos_carta')->nullOnDelete();
            // null = usos ilimitados (lo puede canjear cualquiera, 1 vez cada uno).
            // Un número = tope total de personas distintas que pueden canjearlo.
            $table->unsignedInteger('usos_maximos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('canjes_codigo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_codigo')->constrained('codigos_canje')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->timestamp('canjeado_en');
            $table->timestamps();
            // La garantía real de "1 uso por persona" vive aquí, a nivel de base de
            // datos — no depende de que la lógica de la aplicación no falle nunca.
            $table->unique(['id_codigo', 'id_usuario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canjes_codigo');
        Schema::dropIfExists('codigos_canje');
    }
};