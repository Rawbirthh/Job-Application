<?php

namespace App\Enums;

enum AuditTargetType: string
{
    case User = 'user';
    case Role = 'role';
    case Permission = 'permission';
    case JobPosting = 'job_posting';
    case Company = 'company';
    case CompanyMember = 'company_member';
    case Report = 'report';
    case AuditLog = 'audit_log';

    /**
     * Get the human-readable label for the audit target type.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Role => 'Role',
            self::Permission => 'Permission',
            self::JobPosting => 'Job posting',
            self::Company => 'Company',
            self::CompanyMember => 'Company member',
            self::Report => 'Report',
            self::AuditLog => 'Audit log',
        };
    }

    /**
     * Get the values of every audit target type.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::User->value,
            self::Role->value,
            self::Permission->value,
            self::JobPosting->value,
            self::Company->value,
            self::CompanyMember->value,
            self::Report->value,
            self::AuditLog->value,
        ];
    }
}
