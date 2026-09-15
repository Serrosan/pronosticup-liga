<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rarezas_probabilidad_liga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->foreignId('id_categoria')->constrained('categorias_carta')->cascadeOnDelete();
            $table->string('rareza'); // Comun | PocoComun | Rara | Legendaria
            $table->unsignedTinyInteger('porcentaje');
            $table->timestamps();

            $table->unique(['id_liga', 'id_categoria', 'rareza']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rarezas_probabilidad_liga');
    }
};