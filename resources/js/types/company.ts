import type { PaginatedData } from './authorization';

export type Company = {
    id: number;
    name: string;
    slug: string;
    overview: string | null;
    industry: string | null;
    website: string | null;
    size_band: string | null;
    location: string | null;
    logo_path: string | null;
    logo_url: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type CompanyForm = {
    name: string;
    slug: string;
    overview: string | null;
    industry: string | null;
    website: string | null;
    size_band: string | null;
    location: string | null;
    logo: File | null;
    remove_logo: boolean;
    is_active: boolean;
};

export type CompanyIndexProps = {
    companies: PaginatedData<
        Pick<
            Company,
            | 'id'
            | 'name'
            | 'slug'
            | 'is_active'
            | 'logo_url'
            | 'created_at'
            | 'updated_at'
        >
    >;
    canCreate: boolean;
};

export type CompanyMemberRole = 'owner' | 'recruiter' | 'reviewer';
export type CompanyMemberStatus = 'active' | 'pending' | 'revoked';

export type CompanyMember = {
    id: number;
    user_id: number;
    name: string | null;
    email: string | null;
    company_role: CompanyMemberRole;
    status: CompanyMemberStatus;
    created_at: string | null;
};

export type CompanyMembersIndexProps = {
    company: Pick<Company, 'id' | 'name'>;
    members: PaginatedData<CompanyMember>;
    canManage: boolean;
    roles: CompanyMemberRole[];
};

export type CompanyShowProps = {
    company: Company;
    isOwner?: boolean;
    canManageMembers?: boolean;
};
export type CompanyEditProps = { company: Company };
