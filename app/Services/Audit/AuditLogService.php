<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Job;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The single write path for the administrative audit trail.
 *
 * Every audited action goes through this service so that the target vocabulary,
 * the metadata redaction rules and the request correlation capture stay identical
 * across all call sites. The service never accepts a request or a raw payload.
 */
class AuditLogService
{
    /**
     * Maximum length of a stored metadata string.
     */
    private const MAX_STRING_LENGTH = 200;

    /**
     * Maximum number of entries kept in a stored metadata array.
     */
    private const MAX_ARRAY_ENTRIES = 20;

    /**
     * Maximum nesting depth of a stored metadata array.
     */
    private const MAX_NESTING = 3;

    /**
     * Case-insensitive key fragments that are never stored.
     *
     * @var list<string>
     */
    private const DENIED_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'session',
        'cookie',
        'remember_token',
        'authorization',
        'credential',
        'resume',
        'cover_letter',
        'two_factor',
        'otp',
        'recovery_code',
        'file_path',
    ];

    /**
     * Record an audited action for a model target.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(AuditAction $action, Model $target, array $metadata = [], ?User $actor = null): AuditLog
    {
        [$targetType, $targetLabel] = $this->describeTarget($target);

        return $this->write(
            action: $action,
            targetType: $targetType,
            targetId: $this->numericKey($target),
            targetLabel: $targetLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * Record an audited action for a target that has no (or must not load a) model row.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordWithoutModel(
        AuditAction $action,
        string $targetType,
        ?int $targetId = null,
        ?string $targetLabel = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        if (! in_array($targetType, AuditTargetType::values(), true)) {
            throw new RuntimeException("Audit target type [{$targetType}] is not part of the audit vocabulary.");
        }

        return $this->write(
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            targetLabel: $targetLabel,
            metadata: $metadata,
            actor: $actor,
        );
    }

    /**
     * Resolve the target type and a human-readable label for a model target.
     *
     * @return array{0: string, 1: string|null}
     */
    private function describeTarget(Model $target): array
    {
        return match ($target::class) {
            User::class => [
                AuditTargetType::User->value,
                $this->stringAttribute($target, 'email'),
            ],
            Role::class => [
                AuditTargetType::Role->value,
                $this->stringAttribute($target, 'slug'),
            ],
            Permission::class => [
                AuditTargetType::Permission->value,
                $this->stringAttribute($target, 'slug'),
            ],
            Job::class => [
                AuditTargetType::JobPosting->value,
                $this->stringAttribute($target, 'title'),
            ],
            Company::class => [
                AuditTargetType::Company->value,
                $this->stringAttribute($target, 'name'),
            ],
            Report::class => [
                AuditTargetType::Report->value,
                'Report #'.$target->getKey(),
            ],
            default => throw new RuntimeException(
                sprintf('Model [%s] cannot be used as an audit target.', $target::class),
            ),
        };
    }

    /**
     * Persist a sanitized audit entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function write(
        AuditAction $action,
        string $targetType,
        ?int $targetId,
        ?string $targetLabel,
        array $metadata,
        ?User $actor,
    ): AuditLog {
        $resolvedActor = $actor ?? $this->currentActor();

        [$requestId, $ipAddress] = $this->requestContext();

        $sanitizedMetadata = $this->sanitizeMetadata($metadata);

        return AuditLog::query()->create([
            'actor_id' => $resolvedActor?->getKey(),
            'actor_email' => $resolvedActor?->email,
            'action' => $action->value,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_label' => $targetLabel,
            'metadata' => $sanitizedMetadata === [] ? null : $sanitizedMetadata,
            'ip_address' => $ipAddress,
            'request_id' => $requestId,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Resolve the actor from the current authentication state, if any.
     */
    private function currentActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Capture the request correlation id and the source IP address.
     *
     * Only a request that went through the web middleware stack carries the
     * server-generated correlation id, so console and queue contexts — where the
     * console kernel binds a synthetic request — record neither value.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function requestContext(): array
    {
        if (! app()->bound('request')) {
            return [null, null];
        }

        $requestId = request()->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            return [null, null];
        }

        $ipAddress = request()->ip();

        return [
            $requestId,
            is_string($ipAddress) && $ipAddress !== '' ? $ipAddress : null,
        ];
    }

    /**
     * Sanitize metadata: denied keys are dropped, values are size- and depth-capped.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;

            if ($this->isDeniedKey($key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($value, 1);
        }

        return $sanitized;
    }

    /**
     * Determine whether a metadata key must never be stored.
     */
    private function isDeniedKey(string $key): bool
    {
        $normalized = mb_strtolower($key);

        foreach (self::DENIED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a single metadata value.
     */
    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if (is_string($value)) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($depth > self::MAX_NESTING) {
                return [];
            }

            $entries = array_slice($value, 0, self::MAX_ARRAY_ENTRIES, true);

            $sanitized = [];

            foreach ($entries as $key => $item) {
                $key = (string) $key;

                if ($this->isDeniedKey($key)) {
                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($item, $depth + 1);
            }

            return $sanitized;
        }

        throw new RuntimeException('Audit metadata values must be scalars or arrays.');
    }

    /**
     * Read a string attribute from a model, tolerating partially loaded models.
     */
    private function stringAttribute(Model $target, string $attribute): ?string
    {
        $value = $target->getAttribute($attribute);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Resolve the numeric primary key of a target, when it has one.
     */
    private function numericKey(Model $target): ?int
    {
        $key = $target->getKey();

        return is_int($key) || (is_string($key) && ctype_digit($key)) ? (int) $key : null;
    }
}
