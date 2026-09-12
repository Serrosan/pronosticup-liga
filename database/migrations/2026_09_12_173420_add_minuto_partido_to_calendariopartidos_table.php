<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendariopartidos', function (Blueprint $table) {
            $table->unsignedSmallInteger('minuto_partido')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('calendariopartidos', function (Blueprint $table) {
            $table->dropColumn('minuto_partido');
        });
    }
};