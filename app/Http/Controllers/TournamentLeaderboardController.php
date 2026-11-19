<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TournamentLeaderboardController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        $participants = $tournament->participants()
            ->select(['id', 'user_id'])
            ->withSum('predictions as total_points', 'points')
            ->get();

        // Use stable tournament-local labels instead of exposing account details.
        $participantLabels = $participants
            ->sortBy('id')
            ->values()
            ->mapWithKeys(fn ($participant, $index) => [
                $participant->id => 'Deelnemer '.($index + 1),
            ]);

        $currentUserId = $request->user()?->id;
        $participants = $participants
            ->sort(function ($left, $right) {
                $pointsOrder = (int) ($right->total_points ?? 0) <=> (int) ($left->total_points ?? 0);

                return $pointsOrder !== 0 ? $pointsOrder : $left->id <=> $right->id;
            })
            ->values();

        $previousPoints = null;
        $rank = 0;
        $leaderboard = $participants->map(function ($participant, $index) use (
            $currentUserId,
            $participantLabels,
            &$previousPoints,
            &$rank,
        ) {
            $points = (int) ($participant->total_points ?? 0);

            if ($previousPoints !== $points) {
                $rank = $index + 1;
                $previousPoints = $points;
            }

            $isCurrentUser = $currentUserId !== null
                && (int) $participant->user_id === (int) $currentUserId;

            return [
                'rank' => $rank,
                'label' => $isCurrentUser ? 'Jij' : $participantLabels->get($participant->id),
                'points' => $points,
                'is_current_user' => $isCurrentUser,
            ];
        });

        return view('football-pool.leaderboard', compact('tournament', 'leaderboard'));
    }
}
