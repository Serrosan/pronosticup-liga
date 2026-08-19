<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('nombre_visible');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('nombre_visible')->nullable();
        });
    }
};