<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::AuditLogsView);
    }

    public function view(User $actor, AuditLog $auditLog): bool
    {
        return $actor->hasPermission(PermissionSlug::AuditLogsView);
    }
}
