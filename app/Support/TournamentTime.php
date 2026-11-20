<?php

namespace App\Support;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TournamentTime
{
    // Form values use Amsterdam time while timestamps are stored in UTC.
    public const TIMEZONE = 'Europe/Amsterdam';

    public static function validateLocalInput(string $attribute, string $value, Closure $fail): void
    {
        // PHP moves a time in the skipped spring hour forward instead of rejecting it.
        if (self::fromLocalInput($value)->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i') !== $value) {
            $fail('Dit tijdstip bestaat niet door de overgang naar zomertijd. Kies een ander tijdstip.');
        }
    }

    public static function fromLocalInput(string $value): Carbon
    {
        $date = Carbon::createFromFormat('!Y-m-d\TH:i', $value, self::TIMEZONE);

        if ($date === false) {
            throw new InvalidArgumentException('Tournament date and time must use the local date-time form format.');
        }

        return $date->utc();
    }

    public static function toLocalInput(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : self::formatLocal($value, 'Y-m-d\TH:i');
    }

    public static function formatLocal(DateTimeInterface $value, string $format): string
    {
        return Carbon::instance($value)
            ->setTimezone(self::TIMEZONE)
            ->format($format);
    }
}
