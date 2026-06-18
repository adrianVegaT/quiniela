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
        Schema::table('predictions', function (Blueprint $table) {
            $table->integer('exact_score_points')->nullable()->default(null);
            $table->integer('winner_draw_points')->nullable()->default(null);
            $table->integer('one_team_goals_points')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['exact_score_points', 'winner_draw_points', 'one_team_goals_points']);
        });
    }
};
