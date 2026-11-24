<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CharityMagicLinkService
{
    private const TOKEN_TTL_MINUTES = 5;

    /**
     * Return a token for the email link while storing only its hash.
     */
    public function issueToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put(
            $this->cacheKey($email),
            hash('sha256', $token),
            now()->addMinutes(self::TOKEN_TTL_MINUTES),
        );

        return $token;
    }

    /**
     * Consume a matching, unexpired token exactly once.
     */
    public function consumeToken(string $email, string $token): bool
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
            return false;
        }

        $cacheKey = $this->cacheKey($email);

        // The shared cache lock prevents concurrent requests from redeeming one link twice.
        return Cache::lock($cacheKey.':consume-lock', 10)->get(function () use ($cacheKey, $token): bool {
            $storedHash = Cache::get($cacheKey);

            if (! is_string($storedHash) || ! hash_equals($storedHash, hash('sha256', $token))) {
                return false;
            }

            Cache::forget($cacheKey);

            return true;
        }) === true;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function cacheKey(string $email): string
    {
        return 'charity-magic-link:'.hash('sha256', $this->normalizeEmail($email));
    }
}
