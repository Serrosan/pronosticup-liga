<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nombre_corto')->nullable();
            $table->string('apodo')->nullable();
            $table->string('ciudad')->nullable();
            $table->foreignId('id_estadio')->nullable()->constrained('estadios');
            $table->integer('año_fundacion')->nullable();
            $table->string('escudo_url')->nullable();
            $table->string('color_primario')->nullable();
            $table->string('color_secundario')->nullable();
            $table->string('id_externo_api')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};