<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trofeos', function (Blueprint $table) {
            $table->string('tipo')->nullable();
            $table->string('logo')->nullable();
            $table->string('imagen')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trofeos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'logo', 'imagen']);
        });
    }
};