<?php

namespace App\Http\Controllers;

use App\Mail\CharityMagicLinkMail;
use App\Rules\AllowedCharityEmailDomain;
use App\Services\CharityMagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CharityMagicLoginController extends Controller
{
    public function show(Request $request): View
    {
        return view('charity.auth.login', [
            'activeEmail' => $this->activeEmail($request),
        ]);
    }

    public function sendLink(Request $request, CharityMagicLinkService $magicLink): RedirectResponse
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', new AllowedCharityEmailDomain],
        ]);

        $token = $magicLink->issueToken($validated['email']);
        $url = $magicLink->createSignedUrl($validated['email'], $token);

        Mail::to($validated['email'])->send(new CharityMagicLinkMail($url));

        return redirect()
            ->route('charity.login')
            ->with('status', 'Als het e-mailadres is toegestaan, hebben we een inloglink verstuurd.');
    }

    public function verify(Request $request, CharityMagicLinkService $magicLink): RedirectResponse
    {
        $email = $request->query('email');
        $token = $request->query('token');

        if (! is_string($email) || ! is_string($token) || ! $this->allowedEmail($email) || ! $magicLink->consumeToken($email, $token)) {
            abort(403, 'Deze inloglink is ongeldig of verlopen.');
        }

        // Keep this identity separate from the ordinary Zijpalm authentication guard.
        $request->session()->regenerate(true);
        $request->session()->put('charity_magic', [
            'email' => mb_strtolower(trim($email)),
            'expires_at' => now()->addHours(24)->timestamp,
        ]);

        return redirect()
            ->route('charity.login')
            ->with('status', 'Je bent ingelogd voor Lief & Leed.');
    }

    public function confirm(Request $request): Response
    {
        // Mail scanners can follow the link without using up the token.
        return response()->view('charity.auth.confirm', [
            'confirmUrl' => $request->fullUrl(),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Leave any ordinary Zijpalm login in place.
        $request->session()->forget('charity_magic');
        $request->session()->regenerate(true);

        return redirect()
            ->route('charity.login')
            ->with('status', 'Je bent uitgelogd bij Lief & Leed.');
    }

    private function activeEmail(Request $request): ?string
    {
        $charitySession = $request->session()->get('charity_magic');

        if (
            ! is_array($charitySession)
            || ! is_string($charitySession['email'] ?? null)
            || ! $this->allowedEmail($charitySession['email'])
            || ! is_numeric($charitySession['expires_at'] ?? null)
            || (int) $charitySession['expires_at'] <= now()->timestamp
        ) {
            $request->session()->forget('charity_magic');

            return null;
        }

        return $charitySession['email'];
    }

    private function allowedEmail(string $email): bool
    {
        return Validator::make(['email' => $email], [
            'email' => ['required', new AllowedCharityEmailDomain],
        ])->passes();
    }
}
