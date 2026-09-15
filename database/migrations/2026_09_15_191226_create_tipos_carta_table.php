<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_carta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_categoria')->constrained('categorias_carta')->cascadeOnDelete();
            $table->string('rareza');
            $table->string('nombre');
            $table->text('descripcion');
            $table->string('codigo_efecto');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_carta');
    }
};