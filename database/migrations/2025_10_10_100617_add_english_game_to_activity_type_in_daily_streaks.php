<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para incluir 'english_game'
        DB::statement("ALTER TABLE daily_streaks MODIFY COLUMN activity_type ENUM('typing', 'tutor', 'general', 'english_game') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE daily_streaks MODIFY COLUMN activity_type ENUM('typing', 'tutor', 'general') NOT NULL");
    }
};
