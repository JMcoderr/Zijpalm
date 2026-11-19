<?php

use App\Services\ScoreCalculator;

it('awards four points for an exact score and one for the correct outcome', function () {
    $calculator = new ScoreCalculator;

    expect($calculator->calculate(2, 1, 2, 1))->toBe(4)
        ->and($calculator->calculate(3, 1, 2, 1))->toBe(1)
        ->and($calculator->calculate(1, 3, 2, 1))->toBe(0);
});

it('uses the penalty winner to score tied knockout predictions', function () {
    $calculator = new ScoreCalculator;

    expect($calculator->calculate(1, 1, 1, 1, 'home', 'home'))->toBe(4)
        ->and($calculator->calculate(1, 1, 1, 1, 'away', 'home'))->toBe(3)
        ->and($calculator->calculate(2, 1, 1, 1, null, 'home'))->toBe(1);
});
