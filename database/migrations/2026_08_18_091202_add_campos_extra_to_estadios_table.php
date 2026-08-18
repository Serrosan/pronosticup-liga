<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estadios', function (Blueprint $table) {
            $table->string('tamanio_campo')->nullable();
            $table->integer('anio_construccion')->nullable();
            $table->integer('anio_ult_remodelacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('estadios', function (Blueprint $table) {
            $table->dropColumn(['tamanio_campo', 'anio_construccion', 'anio_ult_remodelacion']);
        });
    }
};