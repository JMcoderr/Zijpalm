<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isValid($value)) {
            $fail('Vul een geldig IBAN in.');
        }
    }

    public function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $iban = strtoupper((string) preg_replace('/\s+/', '', $value));

        return preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) === 1
            && $this->hasValidChecksum($iban);
    }

    private function hasValidChecksum(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;

            // Keep the remainder small instead of converting the full IBAN to a large integer.
            for ($index = 0; $index < strlen($digits); $index++) {
                $remainder = ($remainder * 10 + (int) $digits[$index]) % 97;
            }
        }

        return $remainder === 1;
    }
}
