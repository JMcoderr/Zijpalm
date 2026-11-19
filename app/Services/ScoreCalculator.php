<?php

namespace App\Services;

use InvalidArgumentException;

class ScoreCalculator
{
    public function calculate(
        int $predictedHomeScore,
        int $predictedAwayScore,
        int $actualHomeScore,
        int $actualAwayScore,
        ?string $predictedPenaltyWinner = null,
        ?string $actualPenaltyWinner = null,
    ): int {
        // A correct outcome earns one point; an exact score adds three more.
        $points = $this->outcome($predictedHomeScore, $predictedAwayScore, $predictedPenaltyWinner)
            === $this->outcome($actualHomeScore, $actualAwayScore, $actualPenaltyWinner)
            ? 1
            : 0;

        if ($predictedHomeScore === $actualHomeScore && $predictedAwayScore === $actualAwayScore) {
            $points += 3;
        }

        return $points;
    }

    private function outcome(int $homeScore, int $awayScore, ?string $penaltyWinner): string
    {
        if ($homeScore > $awayScore) {
            return 'home';
        }

        if ($homeScore < $awayScore) {
            return 'away';
        }

        return match ($penaltyWinner) {
            'home' => 'home',
            'away' => 'away',
            null => 'draw',
            default => throw new InvalidArgumentException('Penalty winner must be home or away.'),
        };
    }
}
