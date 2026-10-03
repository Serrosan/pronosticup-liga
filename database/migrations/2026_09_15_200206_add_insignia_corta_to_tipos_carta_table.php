<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_carta', function (Blueprint $table) {
            $table->string('insignia_corta', 10)->nullable()->after('imagen_url');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_carta', function (Blueprint $table) {
            $table->dropColumn('insignia_corta');
        });
    }
};