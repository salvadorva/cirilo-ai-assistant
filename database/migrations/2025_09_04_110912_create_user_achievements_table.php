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
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->timestamp('unlocked_at');
            $table->json('unlock_data')->nullable(); // datos específicos del desbloqueo
            $table->boolean('is_showcased')->default(false); // si el usuario lo muestra en su perfil
            $table->timestamps();

            $table->unique(['user_id', 'achievement_id']);
            $table->index(['user_id', 'unlocked_at']);
            $table->index(['achievement_id', 'unlocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
