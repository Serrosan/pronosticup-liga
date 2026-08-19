<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('nombre_camiseta')->nullable();
            $table->string('posicion_detallada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn(['nombre_camiseta', 'posicion_detallada']);
        });
    }
};