<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unique(
                ['tournament_id', 'home_team_id', 'away_team_id', 'kickoff'],
                'matches_unique_fixture',
            );
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique('matches_unique_fixture');
        });
    }
};
