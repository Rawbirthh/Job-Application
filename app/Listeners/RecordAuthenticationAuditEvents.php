<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\ShouldBeDiscovered;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records the successful authentication events in the audit trail.
 *
 * These writes are fail-soft: an audit problem must never break a login, a
 * logout or a password reset. The password of a reset is never read or stored.
 *
 * The listener opts out of Laravel's event auto-discovery because it is
 * registered explicitly in the AppServiceProvider; discovering it as well would
 * write every authentication event twice.
 */
class RecordAuthenticationAuditEvents implements ShouldBeDiscovered
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Determine whether the listener should be registered during event discovery.
     */
    public static function shouldBeDiscovered(): bool
    {
        return false;
    }

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // The Login event fires before the guard sets the authenticated user,
        // so the actor is passed explicitly.
        $this->record(
            AuditAction::AuthLoggedIn,
            $user,
            ['remember' => (bool) $event->remember],
            $user,
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->record(AuditAction::AuthLoggedOut, $user, [], $user);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->record(AuditAction::AuthPasswordReset, $user, [], $user);
    }

    /**
     * Write an authentication audit entry, ignoring any failure.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function record(AuditAction $action, User $user, array $metadata, User $actor): void
    {
        try {
            $this->auditLogService->record($action, $user, $metadata, $actor);
        } catch (Throwable $exception) {
            Log::error('Authentication audit event could not be recorded.', [
                'action' => $action->value,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
