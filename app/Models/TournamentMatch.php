<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'home_source_match_id',
        'away_source_match_id',
        'stage',
        'round_number',
        'match_number',
        'kickoff',
        'home_score',
        'away_score',
        'penalty_winner',
        'result_finalized',
        'result_locked',
        'result_reminder_sent_at',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'match_number' => 'integer',
        'home_source_match_id' => 'integer',
        'away_source_match_id' => 'integer',
        'kickoff' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'result_finalized' => 'boolean',
        'result_locked' => 'boolean',
        'result_reminder_sent_at' => 'datetime',
    ];

    public function scopeMissingResult(Builder $query): Builder
    {
        return $query
            ->where('kickoff', '<=', now('UTC'))
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->where(function (Builder $query) {
                $query->where('result_finalized', false)
                    ->orWhereNull('home_score')
                    ->orWhereNull('away_score');
            });
    }

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
        return $this->kickoff;
    }
}
