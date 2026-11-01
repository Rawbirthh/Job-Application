<?php

namespace App\Http\Middleware;

use App\Enums\EmployerStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployerIsApproved
{
    /**
     * Require the authenticated user to be an approved employer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status !== EmployerStatus::Approved) {
            abort(403);
        }

        return $next($request);
    }
}
