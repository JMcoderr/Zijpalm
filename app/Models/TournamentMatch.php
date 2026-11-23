<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TournamentMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'tournament_id',
        'tournament_group_id',
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
        'result_locked',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'match_number' => 'integer',
        'kickoff' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'result_finalized' => 'boolean',
        'result_locked' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'tournament_group_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(TournamentPrediction::class, 'match_id');
    }

    public function predictionDeadline(): Carbon
    {
        $deadline = $this->kickoff;
        $tournamentStart = $this->stage === 'group'
            ? $this->tournament?->starts_at
            : null;

        return $tournamentStart && $tournamentStart->lessThan($deadline)
            ? $tournamentStart
            : $deadline;
    }
}
