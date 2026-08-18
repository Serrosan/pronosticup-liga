<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrenadores', function (Blueprint $table) {
            $table->string('apellidos')->nullable();
            $table->string('imagen_foto')->nullable();
            $table->string('ciudad_nacimiento')->nullable();
            $table->string('pais_nacimiento')->nullable();
            $table->date('fecha_inicio_cargo')->nullable();
            $table->date('fecha_fin_cargo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entrenadores', function (Blueprint $table) {
            $table->dropColumn(['apellidos', 'imagen_foto', 'ciudad_nacimiento', 'pais_nacimiento', 'fecha_inicio_cargo', 'fecha_fin_cargo']);
        });
    }
};