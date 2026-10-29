<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Require every given permission for the authenticated user.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
