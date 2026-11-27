<?php

namespace App\Http\Controllers;

use App\Mail\CharityMagicLinkMail;
use App\Models\CharityAdmin;
use App\Rules\AllowedCharityEmailDomain;
use App\Services\CharityMagicLinkService;
use App\Services\CharitySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CharityMagicLoginController extends Controller
{
    public function show(Request $request, CharitySessionService $charitySession): View
    {
        $activeEmail = $charitySession->activeEmail($request);
        $admin = $activeEmail === null
            ? null
            : CharityAdmin::query()->where('employee', $activeEmail)->first();

        return view('charity.auth.login', [
            'activeEmail' => $activeEmail,
            'isAdmin' => $admin !== null,
            'isSuperAdmin' => $admin?->hasSuperAccess() ?? false,
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

    public function verify(
        Request $request,
        CharityMagicLinkService $magicLink,
        CharitySessionService $charitySession,
    ): RedirectResponse {
        $email = $request->query('email');
        $token = $request->query('token');

        if (! is_string($email) || ! is_string($token) || ! $charitySession->isAllowedEmail($email) || ! $magicLink->consumeToken($email, $token)) {
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
        $request->session()->forget([
            'charity_magic',
            CharitySessionService::PAYMENT_DEBTOR_SESSION_KEY,
        ]);
        $request->session()->regenerate(true);

        return redirect()
            ->route('charity.login')
            ->with('status', 'Je bent uitgelogd bij Lief & Leed.');
    }
}
