<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPrediction extends Model
{
    protected $fillable = [
        'tournament_participant_id',
        'match_id',
        'home_score',
        'away_score',
        'penalty_winner',
    ];

    protected $casts = [
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'tournament_participant_id');
    }

    public function tournamentMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }
}
