<?php

namespace App\Http\Middleware\Auth;

use App\Services\CharitySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCharitySession
{
    private CharitySessionService $sessions;

    public function __construct(CharitySessionService $sessions)
    {
        $this->sessions = $sessions;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sessions->activeEmail($request) === null) {
            return redirect()->route('charity.login');
        }

        return $next($request);
    }
}
