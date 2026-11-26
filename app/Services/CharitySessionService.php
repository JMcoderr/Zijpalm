<?php

namespace App\Services;

use App\Rules\AllowedCharityEmailDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CharitySessionService
{
    public function activeEmail(Request $request): ?string
    {
        $charitySession = $request->session()->get('charity_magic');

        if (
            ! is_array($charitySession)
            || ! is_string($charitySession['email'] ?? null)
            || ! $this->isAllowedEmail($charitySession['email'])
            || ! is_numeric($charitySession['expires_at'] ?? null)
            || (int) $charitySession['expires_at'] <= now()->timestamp
        ) {
            $request->session()->forget('charity_magic');

            return null;
        }

        return mb_strtolower(trim($charitySession['email']));
    }

    public function isAllowedEmail(string $email): bool
    {
        return Validator::make(['email' => $email], [
            'email' => ['required', new AllowedCharityEmailDomain],
        ])->passes();
    }
}
