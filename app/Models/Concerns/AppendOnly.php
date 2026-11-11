<?php

namespace App\Models\Concerns;

use App\Exceptions\AuditLogImmutableException;
use Illuminate\Database\Eloquent\Model;

/**
 * Makes a model append-only: updates and deletes are rejected at the model level.
 *
 * This mirrors the ProtectsSystemDefinition pattern: the guard lives in the model
 * and not in the database. Bulk query-builder deletes and truncates bypass model
 * events, so the absence of any write route is the enforced boundary.
 */
trait AppendOnly
{
    protected static function bootAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw AuditLogImmutableException::cannotModify();
        });

        static::deleting(function (Model $model): void {
            throw AuditLogImmutableException::cannotDelete();
        });
    }
}
