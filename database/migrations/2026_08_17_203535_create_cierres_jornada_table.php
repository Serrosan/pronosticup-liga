<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_jornada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_liga')->constrained('ligas');
            $table->integer('jornada');
            $table->boolean('cerrada')->default(false);
            $table->timestamp('cerrada_en')->nullable();
            $table->foreignId('cerrada_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['id_liga', 'jornada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_jornada');
    }
};