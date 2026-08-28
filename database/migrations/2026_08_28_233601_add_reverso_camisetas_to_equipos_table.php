<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('camiseta_1_reverso')->nullable()->after('camiseta_1');
            $table->string('camiseta_2_reverso')->nullable()->after('camiseta_2');
            $table->string('camiseta_3_reverso')->nullable()->after('camiseta_3');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['camiseta_1_reverso', 'camiseta_2_reverso', 'camiseta_3_reverso']);
        });
    }
};