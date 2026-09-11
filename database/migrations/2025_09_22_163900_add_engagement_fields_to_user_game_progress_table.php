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
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->integer('days_inactive')->default(0)->after('longest_streak');
            $table->decimal('engagement_score', 5, 2)->default(0.00)->after('days_inactive');
            $table->enum('churn_risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->after('engagement_score');
            $table->timestamp('last_activity_at')->nullable()->after('churn_risk_level');
            $table->integer('sessions_this_week')->default(0)->after('last_activity_at');
            $table->integer('total_login_days')->default(0)->after('sessions_this_week');

            $table->index(['churn_risk_level', 'last_activity_at']);
            $table->index(['engagement_score', 'days_inactive']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_game_progress', function (Blueprint $table) {
            $table->dropIndex(['churn_risk_level', 'last_activity_at']);
            $table->dropIndex(['engagement_score', 'days_inactive']);
            $table->dropColumn([
                'days_inactive',
                'engagement_score',
                'churn_risk_level',
                'last_activity_at',
                'sessions_this_week',
                'total_login_days',
            ]);
        });
    }
};
