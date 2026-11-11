<?php

namespace App\Enums;

enum AuditAction: string
{
    case EmployerApproved = 'employer.approved';
    case EmployerRejected = 'employer.rejected';
    case EmployerSuspended = 'employer.suspended';
    case EmployerReactivated = 'employer.reactivated';
    case JobApproved = 'job.approved';
    case JobRejected = 'job.rejected';
    case JobClosed = 'job.closed';
    case ReportInvestigated = 'report.investigated';
    case ReportResolved = 'report.resolved';
    case ReportDismissed = 'report.dismissed';
    case UserRolesUpdated = 'user.roles_updated';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case PermissionCreated = 'permission.created';
    case PermissionUpdated = 'permission.updated';
    case PermissionDeleted = 'permission.deleted';
    case CompanyMemberAdded = 'company_member.added';
    case CompanyMemberRoleChanged = 'company_member.role_changed';
    case CompanyMemberRevoked = 'company_member.revoked';
    case AccountDeleted = 'account.deleted';
    case AuthLoggedIn = 'auth.logged_in';
    case AuthLoggedOut = 'auth.logged_out';
    case AuthPasswordReset = 'auth.password_reset';
    case AuditLogViewed = 'audit_log.viewed';

    /**
     * Get the human-readable label for the audit action.
     */
    public function label(): string
    {
        return match ($this) {
            self::EmployerApproved => 'Employer approved',
            self::EmployerRejected => 'Employer rejected',
            self::EmployerSuspended => 'Employer suspended',
            self::EmployerReactivated => 'Employer reactivated',
            self::JobApproved => 'Job approved',
            self::JobRejected => 'Job rejected',
            self::JobClosed => 'Job closed',
            self::ReportInvestigated => 'Report under investigation',
            self::ReportResolved => 'Report resolved',
            self::ReportDismissed => 'Report dismissed',
            self::UserRolesUpdated => 'User roles updated',
            self::RoleCreated => 'Role created',
            self::RoleUpdated => 'Role updated',
            self::RoleDeleted => 'Role deleted',
            self::PermissionCreated => 'Permission created',
            self::PermissionUpdated => 'Permission updated',
            self::PermissionDeleted => 'Permission deleted',
            self::CompanyMemberAdded => 'Company member added',
            self::CompanyMemberRoleChanged => 'Company member role changed',
            self::CompanyMemberRevoked => 'Company member revoked',
            self::AccountDeleted => 'Account deleted',
            self::AuthLoggedIn => 'Logged in',
            self::AuthLoggedOut => 'Logged out',
            self::AuthPasswordReset => 'Password reset',
            self::AuditLogViewed => 'Audit log viewed',
        };
    }

    /**
     * Get the values of every audit action.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::EmployerApproved->value,
            self::EmployerRejected->value,
            self::EmployerSuspended->value,
            self::EmployerReactivated->value,
            self::JobApproved->value,
            self::JobRejected->value,
            self::JobClosed->value,
            self::ReportInvestigated->value,
            self::ReportResolved->value,
            self::ReportDismissed->value,
            self::UserRolesUpdated->value,
            self::RoleCreated->value,
            self::RoleUpdated->value,
            self::RoleDeleted->value,
            self::PermissionCreated->value,
            self::PermissionUpdated->value,
            self::PermissionDeleted->value,
            self::CompanyMemberAdded->value,
            self::CompanyMemberRoleChanged->value,
            self::CompanyMemberRevoked->value,
            self::AccountDeleted->value,
            self::AuthLoggedIn->value,
            self::AuthLoggedOut->value,
            self::AuthPasswordReset->value,
            self::AuditLogViewed->value,
        ];
    }
}
