<?php

namespace App\Models\Concerns;

use App\Exceptions\ProtectedSystemDefinitionException;
use Illuminate\Database\Eloquent\Model;

trait ProtectsSystemDefinition
{
    protected static function bootProtectsSystemDefinition(): void
    {
        static::deleting(function (Model $model): void {
            if (self::persistedSystemFlag($model) || (bool) $model->getAttribute('is_system')) {
                throw ProtectedSystemDefinitionException::cannotDelete(
                    strtolower(class_basename($model)),
                    (string) $model->getAttribute('slug'),
                );
            }
        });

        static::updating(function (Model $model): void {
            if (! self::persistedSystemFlag($model)) {
                return;
            }

            $type = strtolower(class_basename($model));
            $slug = (string) $model->getAttribute('slug');

            if ($model->isDirty('slug')) {
                throw ProtectedSystemDefinitionException::cannotChangeSlug($type, $slug);
            }

            if ($model->isDirty('is_system') && ! (bool) $model->getAttribute('is_system')) {
                throw ProtectedSystemDefinitionException::cannotClearSystemFlag($type, $slug);
            }
        });
    }

    /**
     * Resolve the persisted is_system flag, hydrating it when the column was not selected.
     */
    private static function persistedSystemFlag(Model $model): bool
    {
        $original = $model->getOriginal('is_system');

        if ($original !== null) {
            return (bool) $original;
        }

        if ($model->getKey() === null) {
            return false;
        }

        $persisted = $model->newQuery()
            ->whereKey($model->getKey())
            ->first(['is_system']);

        return (bool) $persisted?->getAttribute('is_system');
    }
}
