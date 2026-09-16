<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_top3_probabilidad_liga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas')->cascadeOnDelete();
            $table->unsignedTinyInteger('posicion'); // 1, 2 o 3
            $table->string('rareza');
            $table->unsignedTinyInteger('porcentaje');
            $table->timestamps();

            $table->unique(['id_liga', 'posicion', 'rareza']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_top3_probabilidad_liga');
    }
};