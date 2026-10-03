<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->string('mensaje_falta', 200)->nullable()->after('jornada_efecto');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_usuario', function (Blueprint $table) {
            $table->dropColumn('mensaje_falta');
        });
    }
};