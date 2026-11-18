<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->restrictOnDelete();
            $table->string('stage', 32)->default('group');
            $table->unsignedTinyInteger('round_number')->default(1);
            $table->unsignedSmallInteger('match_number');
            $table->dateTime('kickoff');
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->string('penalty_winner', 4)->nullable();
            $table->boolean('result_finalized')->default(false);
            $table->timestamps();

            $table->unique(['tournament_id', 'stage', 'round_number', 'match_number']);
            $table->index(['tournament_id', 'kickoff']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
