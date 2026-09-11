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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('level')->default('beginner'); // beginner, intermediate, advanced
            $table->integer('sessions_count')->default(0);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active'); // active, inactive, completed
            $table->text('image_description')->nullable();
            $table->text('audio_script')->nullable();
            $table->boolean('created_by_ai')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
