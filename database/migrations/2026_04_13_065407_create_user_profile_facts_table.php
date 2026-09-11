<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category');  // personal_info | preferences | work_context | relationships | goals | facts
            $table->string('key');       // snake_case: job_title, favorite_language, etc.
            $table->text('value');
            $table->float('confidence')->default(1.0); // 0.0 - 1.0
            $table->string('source_type')->default('extracted'); // extracted | user_confirmed
            $table->timestamp('last_mentioned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category', 'key']);
            $table->index(['user_id', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_facts');
    }
};
