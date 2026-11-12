<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Display the paginated audit trail.
     */
    public function index(IndexAuditLogRequest $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validated();

        $action = $this->whitelisted($validated['action'] ?? null, AuditAction::values());
        $targetType = $this->whitelisted($validated['target_type'] ?? null, AuditTargetType::values());
        $actorId = isset($validated['actor_id']) ? (int) $validated['actor_id'] : null;
        $search = trim((string) ($validated['search'] ?? ''));
        $from = is_string($validated['from'] ?? null) ? $validated['from'] : null;
        $to = is_string($validated['to'] ?? null) ? $validated['to'] : null;
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        // The date filters are inclusive: "from" starts at midnight, "to" ends at
        // the end of the day.
        $fromBoundary = $from === null ? null : $from.' 00:00:00';
        $toBoundary = $to === null ? null : $to.' 23:59:59';

        $auditLogs = AuditLog::query()
            ->select([
                'audit_logs.id',
                'audit_logs.actor_id',
                'audit_logs.actor_email',
                'audit_logs.action',
                'audit_logs.target_type',
                'audit_logs.target_id',
                'audit_logs.target_label',
                'audit_logs.ip_address',
                'audit_logs.request_id',
                'audit_logs.occurred_at',
            ])
            ->with('actor:id,name')
            ->when($action !== null, fn (Builder $query) => $query->where('audit_logs.action', $action))
            ->when($targetType !== null, fn (Builder $query) => $query->where('audit_logs.target_type', $targetType))
            ->when($actorId !== null, fn (Builder $query) => $query->where('audit_logs.actor_id', $actorId))
            ->when($fromBoundary !== null, fn (Builder $query) => $query->where('audit_logs.occurred_at', '>=', $fromBoundary))
            ->when($toBoundary !== null, fn (Builder $query) => $query->where('audit_logs.occurred_at', '<=', $toBoundary))
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('audit_logs.actor_email', 'like', $prefix)
                        ->orWhere('audit_logs.target_label', 'like', $prefix);
                });
            })
            ->orderByDesc('audit_logs.occurred_at')
            ->orderByDesc('audit_logs.id')
            ->paginate(25)
            ->withQueryString();

        $auditLogs->through(fn (AuditLog $auditLog): array => [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'action_label' => AuditAction::tryFrom($auditLog->action)?->label() ?? $auditLog->action,
            'target_type' => $auditLog->target_type,
            'target_type_label' => AuditTargetType::tryFrom($auditLog->target_type)?->label() ?? $auditLog->target_type,
            'target_id' => $auditLog->target_id,
            'target_label' => $auditLog->target_label,
            'actor' => $auditLog->actor === null ? null : [
                'id' => $auditLog->actor->id,
                'name' => $auditLog->actor->name,
            ],
            'actor_email' => $auditLog->actor_email,
            'ip_address' => $auditLog->ip_address,
            'request_id' => $auditLog->request_id,
            'occurred_at' => $auditLog->occurred_at->toIso8601String(),
        ]);

        $this->recordView('index');

        return Inertia::render('admin/audit-logs/index', [
            'auditLogs' => $auditLogs,
            'filters' => [
                'action' => $action,
                'target_type' => $targetType,
                'actor_id' => $actorId,
                'search' => $search === '' ? null : $search,
                'from' => $from,
                'to' => $to,
            ],
            'actions' => array_map(
                fn (AuditAction $action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ],
                AuditAction::cases(),
            ),
            'targetTypes' => array_map(
                fn (AuditTargetType $targetType): array => [
                    'value' => $targetType->value,
                    'label' => $targetType->label(),
                ],
                AuditTargetType::cases(),
            ),
        ]);
    }

    /**
     * Display a single audit entry.
     */
    public function show(AuditLog $auditLog): Response
    {
        $this->authorize('view', $auditLog);

        $auditLog->load('actor:id,name');

        $this->recordView('show', $auditLog->id);

        return Inertia::render('admin/audit-logs/show', [
            'auditLog' => [
                'id' => $auditLog->id,
                'action' => $auditLog->action,
                'action_label' => AuditAction::tryFrom($auditLog->action)?->label() ?? $auditLog->action,
                'target_type' => $auditLog->target_type,
                'target_type_label' => AuditTargetType::tryFrom($auditLog->target_type)?->label() ?? $auditLog->target_type,
                'target_id' => $auditLog->target_id,
                'target_label' => $auditLog->target_label,
                'actor' => $auditLog->actor === null ? null : [
                    'id' => $auditLog->actor->id,
                    'name' => $auditLog->actor->name,
                ],
                'actor_email' => $auditLog->actor_email,
                'ip_address' => $auditLog->ip_address,
                'request_id' => $auditLog->request_id,
                'occurred_at' => $auditLog->occurred_at->toIso8601String(),
                'metadata' => $this->metadataPayload($auditLog->metadata),
            ],
        ]);
    }

    /**
     * Record that an administrator read the audit trail.
     *
     * Read-path writes are fail-soft: a logging problem must never break the
     * admin page.
     */
    private function recordView(string $scope, ?int $entryId = null): void
    {
        try {
            $this->auditLogService->recordWithoutModel(
                AuditAction::AuditLogViewed,
                AuditTargetType::AuditLog->value,
                $entryId,
                null,
                $entryId === null
                    ? ['scope' => $scope]
                    : ['scope' => $scope, 'entry_id' => $entryId],
            );
        } catch (\Throwable $exception) {
            Log::error('Audit log view could not be recorded.', [
                'scope' => $scope,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Flatten the stored metadata into a renderable key/value list.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<int, array{key: string, value: string}>
     */
    private function metadataPayload(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $payload = [];

        foreach ($metadata as $key => $value) {
            $payload[] = [
                'key' => (string) $key,
                'value' => $this->metadataValue($value),
            ];
        }

        return $payload;
    }

    /**
     * Render a metadata value as a displayable string.
     */
    private function metadataValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return '';
    }

    /**
     * Keep only filter values that belong to the closed vocabulary.
     *
     * @param  array<int, string>  $allowed
     */
    private function whitelisted(?string $value, array $allowed): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
