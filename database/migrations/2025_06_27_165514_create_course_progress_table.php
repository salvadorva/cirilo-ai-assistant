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
        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('course_sessions')->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->integer('score')->nullable();
            $table->json('feedback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Índices únicos para evitar duplicados
            $table->unique(['user_id', 'course_id', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_progress');
    }
};
