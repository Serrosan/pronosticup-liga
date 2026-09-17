<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cierres_jornada', function (Blueprint $table) {
            $table->timestamp('goleadores_calculados_en')->nullable()->after('cerrada_en');
        });
    }

    public function down(): void
    {
        Schema::table('cierres_jornada', function (Blueprint $table) {
            $table->dropColumn('goleadores_calculados_en');
        });
    }
};