<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Any authenticated user may report (route middleware enforces the auth gate).
     */
    public function create(User $actor): bool
    {
        return true;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::ReportsModerate);
    }

    public function view(User $actor, Report $report): bool
    {
        return $actor->hasPermission(PermissionSlug::ReportsModerate);
    }

    public function investigate(User $actor, Report $report): bool
    {
        return $actor->hasPermission(PermissionSlug::ReportsModerate);
    }

    public function resolve(User $actor, Report $report): bool
    {
        return $actor->hasPermission(PermissionSlug::ReportsModerate);
    }

    public function dismiss(User $actor, Report $report): bool
    {
        return $actor->hasPermission(PermissionSlug::ReportsModerate);
    }
}
