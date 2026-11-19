<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentLeaderboardController extends Controller
{
    public function index(Request $request, Tournament $tournament): View
    {
        $leaderboard = $this->buildLeaderboard($tournament, $request->user()?->id);

        return view('football-pool.leaderboard', compact('tournament', 'leaderboard'));
    }

    public function export(Tournament $tournament): StreamedResponse
    {
        $leaderboard = $this->buildLeaderboard($tournament);

        return response()->streamDownload(function () use ($leaderboard): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the leaderboard export stream.');
            }

            try {
                // Excel needs the BOM to read UTF-8 correctly, and the semicolon delimiter fits Dutch settings.
                if (fwrite($stream, "\xEF\xBB\xBF") === false
                    || fputcsv($stream, ['Rang', 'Deelnemer', 'Punten'], ';', '"', '\\') === false) {
                    throw new \RuntimeException('Unable to write the leaderboard export.');
                }

                foreach ($leaderboard as $entry) {
                    if (fputcsv($stream, [
                        $entry['rank'],
                        $entry['label'],
                        $entry['points'],
                    ], ';', '"', '\\') === false) {
                        throw new \RuntimeException('Unable to write the leaderboard export.');
                    }
                }
            } finally {
                fclose($stream);
            }
        }, 'ranglijst_toernooi_'.$tournament->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildLeaderboard(Tournament $tournament, ?int $currentUserId = null): Collection
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

        return $leaderboard;
    }
}
