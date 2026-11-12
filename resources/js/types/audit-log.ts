import type { PaginatedData } from './authorization';

/**
 * Audit action catalogue mirroring the backend enum (see app/Enums/AuditAction).
 * Keep the slugs in sync with the PHP enum; labels mirror its label() method.
 */
export const AUDIT_ACTIONS = [
    { value: 'employer.approved', label: 'Employer approved' },
    { value: 'employer.rejected', label: 'Employer rejected' },
    { value: 'employer.suspended', label: 'Employer suspended' },
    { value: 'employer.reactivated', label: 'Employer reactivated' },
    { value: 'job.approved', label: 'Job approved' },
    { value: 'job.rejected', label: 'Job rejected' },
    { value: 'job.closed', label: 'Job closed' },
    { value: 'report.investigated', label: 'Report under investigation' },
    { value: 'report.resolved', label: 'Report resolved' },
    { value: 'report.dismissed', label: 'Report dismissed' },
    { value: 'user.roles_updated', label: 'User roles updated' },
    { value: 'role.created', label: 'Role created' },
    { value: 'role.updated', label: 'Role updated' },
    { value: 'role.deleted', label: 'Role deleted' },
    { value: 'permission.created', label: 'Permission created' },
    { value: 'permission.updated', label: 'Permission updated' },
    { value: 'permission.deleted', label: 'Permission deleted' },
    { value: 'company_member.added', label: 'Company member added' },
    {
        value: 'company_member.role_changed',
        label: 'Company member role changed',
    },
    { value: 'company_member.revoked', label: 'Company member revoked' },
    { value: 'account.deleted', label: 'Account deleted' },
    { value: 'auth.logged_in', label: 'Logged in' },
    { value: 'auth.logged_out', label: 'Logged out' },
    { value: 'auth.password_reset', label: 'Password reset' },
    { value: 'audit_log.viewed', label: 'Audit log viewed' },
    { value: 'analytics.viewed', label: 'Analytics viewed' },
] as const;

/**
 * Audit target vocabulary mirroring the backend enum (see
 * app/Enums/AuditTargetType). Keep the values in sync with the PHP enum.
 */
export const AUDIT_TARGET_TYPES = [
    { value: 'user', label: 'User' },
    { value: 'role', label: 'Role' },
    { value: 'permission', label: 'Permission' },
    { value: 'job_posting', label: 'Job posting' },
    { value: 'company', label: 'Company' },
    { value: 'company_member', label: 'Company member' },
    { value: 'report', label: 'Report' },
    { value: 'audit_log', label: 'Audit log' },
    { value: 'analytics', label: 'Analytics' },
] as const;

export type AuditActionSlug = (typeof AUDIT_ACTIONS)[number]['value'];

export type AuditTargetTypeSlug = (typeof AUDIT_TARGET_TYPES)[number]['value'];

/** Values accepted by the admin audit log filters (server-side whitelist). */
export type AuditActionFilterValue = AuditActionSlug | 'all';

export type AuditTargetTypeFilterValue = AuditTargetTypeSlug | 'all';

/** Look up a human-readable label, falling back to the raw value. */
export function auditActionLabel(value: string): string {
    return (
        AUDIT_ACTIONS.find((option) => option.value === value)?.label ?? value
    );
}

export function auditTargetTypeLabel(value: string): string {
    return (
        AUDIT_TARGET_TYPES.find((option) => option.value === value)?.label ??
        value
    );
}

/**
 * An option row of the admin filter selects. The server sends the full
 * catalogue for the current request, so the select never falls behind the
 * backend vocabulary.
 */
export type AuditOption = {
    value: string;
    label: string;
};

/** Actor identity shown on the audit trail (never on public surfaces). */
export type AuditLogActorSummary = {
    id: number;
    name: string;
};

/** One row of the admin audit trail (mirrors Admin\AuditLogController@index). */
export type AuditLogListItem = {
    id: number;
    action: string;
    action_label: string;
    target_type: string;
    target_type_label: string;
    target_id: number | null;
    target_label: string | null;
    actor: AuditLogActorSummary | null;
    actor_email: string | null;
    ip_address: string | null;
    request_id: string | null;
    occurred_at: string;
};

/** One flattened metadata pair (mirrors Admin\AuditLogController@show). */
export type AuditLogMetadataEntry = {
    key: string;
    value: string;
};

/** Full audit entry payload for the admin detail page. */
export type AuditLogDetail = AuditLogListItem & {
    metadata: AuditLogMetadataEntry[];
};

export type AdminAuditLogsIndexProps = {
    auditLogs: PaginatedData<AuditLogListItem>;
    filters: {
        action: string | null;
        target_type: string | null;
        actor_id: number | null;
        search: string | null;
        from: string | null;
        to: string | null;
    };
    actions: AuditOption[];
    targetTypes: AuditOption[];
};

export type AdminAuditLogShowProps = {
    auditLog: AuditLogDetail;
};
