import type { Company } from './company';

export type RoleSummary = {
    id: number;
    slug: string;
    name: string;
};

export type RoleOption = RoleSummary & {
    description: string | null;
    is_system: boolean;
};

export type RoleWithPermissions = RoleSummary & {
    description: string | null;
    is_system: boolean;
    permissions_count: number;
    permission_ids?: number[];
};

export type PermissionSummary = {
    id: number;
    slug: string;
    name: string;
};

export type PermissionWithRoles = PermissionSummary & {
    description: string | null;
    is_system: boolean;
    roles_count: number;
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    roles: RoleSummary[];
};

export type AdminUserIndexProps = {
    users: PaginatedData<AdminUserRow>;
    filters: {
        search: string;
    };
    canManageRoles: boolean;
};

export type AdminUserRolesProps = {
    user: AdminUserRow;
    roles: RoleOption[];
    canViewUsers: boolean;
};

export type AdminRolesIndexProps = {
    roles: PaginatedData<RoleWithPermissions>;
    permissions: PermissionSummary[];
    filters: {
        search: string;
    };
    canManageRoles: boolean;
};

export type AdminPermissionsIndexProps = {
    permissions: PaginatedData<PermissionWithRoles>;
    filters: {
        search: string;
    };
    canManagePermissions: boolean;
};

export type EmployerStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

export type EmployerOnboardingRequestSummary = {
    company_name: string;
    company_website: string | null;
    job_title: string;
    message: string | null;
    submitted_at: string;
    reviewed_at: string | null;
    review_note: string | null;
};

export type EmployerRow = {
    id: number;
    name: string;
    email: string;
    status: EmployerStatus | null;
    status_badge: EmployerStatus | 'not_applied';
    suspension_reason: string | null;
    onboarding_request: Pick<
        EmployerOnboardingRequestSummary,
        'company_name' | 'submitted_at' | 'reviewed_at' | 'review_note'
    > | null;
    company: Company | null;
    can_manage: boolean;
};

export type AdminEmployerIndexProps = {
    employers: PaginatedData<EmployerRow>;
    filters: {
        search: string;
        status: EmployerStatus | null;
    };
    canManageEmployers: boolean;
};

export type AdminEmployerShowProps = {
    employer: {
        id: number;
        name: string;
        email: string;
        status: EmployerStatus | null;
        status_badge: EmployerStatus | 'not_applied';
        suspension_reason: string | null;
        onboarding_request:
            | (EmployerOnboardingRequestSummary & {
                  company_website: string | null;
                  job_title: string;
                  message: string | null;
                  reviewer: { id: number; name: string } | null;
              })
            | null;
        company: Company | null;
        roles: RoleSummary[];
        actions: {
            approve: boolean;
            reject: boolean;
            suspend: boolean;
            reactivate: boolean;
        };
        can_manage: boolean;
    };
    canManageEmployers: boolean;
};

export type EmployerOnboardingPageProps = {
    status: EmployerStatus | null;
    request: EmployerOnboardingRequestSummary | null;
    suspension_reason: string | null;
    can_apply: boolean;
    can_access_employer_dashboard: boolean;
};
