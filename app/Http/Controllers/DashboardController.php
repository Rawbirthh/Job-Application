<?php

namespace App\Http\Controllers;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Redirect authenticated users to their permitted dashboard, or fallback.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasPermission(PermissionSlug::AdminAccess)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole(RoleSlug::Employer)) {
            return redirect()->route('employer.dashboard');
        }

        if ($user->hasRole(RoleSlug::JobSeeker)) {
            return redirect()->route('candidate.dashboard');
        }

        return Inertia::render('dashboard');
    }
}
