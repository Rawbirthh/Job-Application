export type RoleSummary = {
    id: number;
    slug: string;
    name: string;
};

export type RoleOption = RoleSummary & {
    description: string | null;
    is_system: boolean;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    roles: RoleSummary[];
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
