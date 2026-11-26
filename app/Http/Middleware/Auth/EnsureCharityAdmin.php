<?php

namespace App\Http\Middleware\Auth;

use App\Models\CharityAdmin as CharityAdminRecord;
use App\Services\CharitySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCharityAdmin
{
    private CharitySessionService $sessions;

    public function __construct(CharitySessionService $sessions)
    {
        $this->sessions = $sessions;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $email = $this->sessions->activeEmail($request);

        if ($email === null) {
            return redirect()->route('charity.login');
        }

        // Check the current admin record so removed access stops immediately.
        if (! CharityAdminRecord::query()->where('employee', $email)->exists()) {
            abort(403);
        }

        return $next($request);
    }
}
