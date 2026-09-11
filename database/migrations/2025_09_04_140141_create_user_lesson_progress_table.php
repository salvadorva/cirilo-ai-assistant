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
        Schema::create('user_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('typing_lesson_id')->constrained()->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->integer('best_wpm')->default(0);
            $table->decimal('best_accuracy', 5, 2)->default(0);
            $table->integer('attempts')->default(0);
            $table->timestamp('first_attempt')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('attempt_history')->nullable(); // Historial de intentos
            $table->timestamps();

            // Índices
            $table->unique(['user_id', 'typing_lesson_id']);
            $table->index('completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_lesson_progress');
    }
};
