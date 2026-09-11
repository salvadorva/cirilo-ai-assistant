<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('typing_sessions', function (Blueprint $table) {
            $table->string('game_mode')->nullable()->after('mode'); // training, arcade, survival, zen
            $table->integer('mode_score')->nullable()->after('game_mode'); // Puntuación específica del modo
            $table->json('mode_data')->nullable()->after('mode_score'); // Datos adicionales del modo (power-ups, vidas, etc.)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('typing_sessions', function (Blueprint $table) {
            $table->dropColumn(['game_mode', 'mode_score', 'mode_data']);
        });
    }
};
