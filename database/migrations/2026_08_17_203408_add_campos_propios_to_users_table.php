<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre_visible')->nullable()->after('name');
            $table->string('avatar_url')->nullable();
            $table->timestamp('activado_en')->nullable();
            $table->boolean('es_superadmin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombre_visible', 'avatar_url', 'activado_en', 'es_superadmin']);
        });
    }
};