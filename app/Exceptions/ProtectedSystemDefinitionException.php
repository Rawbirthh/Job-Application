<?php

namespace App\Exceptions;

use RuntimeException;

class ProtectedSystemDefinitionException extends RuntimeException
{
    public static function cannotDelete(string $type, string $slug): self
    {
        return new self("System {$type} [{$slug}] cannot be deleted.");
    }

    public static function cannotChangeSlug(string $type, string $slug): self
    {
        return new self("The slug of system {$type} [{$slug}] cannot be changed.");
    }

    public static function cannotClearSystemFlag(string $type, string $slug): self
    {
        return new self("System {$type} [{$slug}] cannot be marked as non-system.");
    }
}
