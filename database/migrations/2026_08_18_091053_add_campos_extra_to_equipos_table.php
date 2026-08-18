<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('siglas')->nullable();
            $table->integer('num_socios')->nullable();
            $table->integer('num_abonados')->nullable();
            $table->string('camiseta_1')->nullable();
            $table->string('camiseta_2')->nullable();
            $table->string('camiseta_3')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['siglas', 'num_socios', 'num_abonados', 'camiseta_1', 'camiseta_2', 'camiseta_3']);
        });
    }
};