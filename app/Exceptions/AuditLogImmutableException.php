<?php

namespace App\Exceptions;

use RuntimeException;

class AuditLogImmutableException extends RuntimeException
{
    public static function cannotModify(): self
    {
        return new self('Audit log entries are append-only and cannot be modified.');
    }

    public static function cannotDelete(): self
    {
        return new self('Audit log entries are append-only and cannot be deleted.');
    }
}
