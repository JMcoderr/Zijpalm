<?php

namespace App\Services;

use App\Models\CharityRequest;
use App\Rules\ValidIban;

class CharityPaymentReadiness
{
    public const MAX_SEPA_PARTY_NAME_LENGTH = 70;

    public function isComplete(CharityRequest $charityRequest): bool
    {
        $accountName = $charityRequest->account_name;

        if (! is_string($accountName)) {
            return false;
        }

        $accountName = trim($accountName);

        return $accountName !== ''
            && mb_strlen($accountName, 'UTF-8') <= self::MAX_SEPA_PARTY_NAME_LENGTH
            && (new ValidIban)->isValid($charityRequest->iban)
            && $this->amountToCents($charityRequest->eventCost?->amount) !== null;
    }

    public function amountToCents(mixed $amount): ?int
    {
        if ((! is_string($amount) && ! is_int($amount) && ! is_float($amount))
            || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', (string) $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        if (strlen($whole) > 11) {
            return null;
        }

        $amountInCents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $amountInCents > 0 ? $amountInCents : null;
    }
}
