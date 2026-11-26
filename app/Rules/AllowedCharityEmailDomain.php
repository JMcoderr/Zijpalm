<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedCharityEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $fail('Vul een geldig e-mailadres in.');

            return;
        }

        $atPosition = strrpos($value, '@');

        if ($atPosition === false) {
            $fail('Vul een geldig e-mailadres in.');

            return;
        }

        $domain = mb_strtolower(substr($value, $atPosition + 1));
        $allowedDomains = config('services.charity_magic_links.allowed_domains', []);
        $allowedDomains = array_map(
            static fn (string $allowedDomain): string => mb_strtolower(trim($allowedDomain)),
            is_array($allowedDomains) ? $allowedDomains : [],
        );

        if (! in_array($domain, $allowedDomains, true)) {
            $fail('Dit e-mailadres is niet toegestaan voor Lief & Leed.');
        }
    }
}
