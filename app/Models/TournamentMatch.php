<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'home_team_id',
        'away_team_id',
        'stage',
        'round_number',
        'match_number',
        'kickoff',
        'home_score',
        'away_score',
        'penalty_winner',
        'result_finalized',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'match_number' => 'integer',
        'kickoff' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'result_finalized' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
