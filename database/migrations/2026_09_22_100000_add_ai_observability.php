<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_usage_logs', function (Blueprint $table) {
            $table->decimal('estimated_cost', 14, 8)->nullable()->default(null)->change();
            $table->uuid('interaction_id')->nullable()->index();
            $table->string('stage', 40)->nullable();
            $table->string('cost_status', 40)->default('legacy_unverified')->index();
            $table->date('pricing_date')->nullable();
        });
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 80);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('result', 40)->default('pending');
            $table->json('stages')->nullable();
            $table->boolean('useful')->nullable();
            $table->boolean('task_achieved')->nullable();
            $table->unsignedSmallInteger('corrections')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Keep nullable cost precision: coercing unknown historical costs to zero
        // would lose information. Downgrading code does not require a rollback.
        Schema::dropIfExists('ai_interactions');
        Schema::table('api_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['interaction_id', 'stage', 'cost_status', 'pricing_date']);
        });
    }
};
