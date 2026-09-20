<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->timestamp('revelada_en')->nullable()->after('obtenida_en');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->dropColumn('revelada_en');
        });
    }
};