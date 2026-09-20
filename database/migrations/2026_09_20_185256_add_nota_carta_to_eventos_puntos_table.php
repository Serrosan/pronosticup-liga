<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_puntos', function (Blueprint $table) {
            $table->string('nota_carta')->nullable()->after('puntos');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_puntos', function (Blueprint $table) {
            $table->dropColumn('nota_carta');
        });
    }
};