export type Job = {
    id: number;
    company_id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    category_id: number;
    employment_type_id: number;
    workplace_arrangement_id: number | null;
    experience_level_id: number | null;
    salary_min: string | null;
    salary_max: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    pay_period_id: number | null;
    location: string | null;
    application_deadline: string | null;
    status: string;
    rejection_reason: string | null;
    applications_count?: number;
    created_at: string;
    updated_at: string;
    company?: CompanyOption;
    category?: CatalogueOption;
    employment_type?: CatalogueOption;
};

export type JobForm = {
    company_id: number | null;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    category_id: number | null;
    employment_type_id: number | null;
    workplace_arrangement_id: number | null;
    experience_level_id: number | null;
    salary_min: string;
    salary_max: string;
    salary_currency: string;
    salary_negotiable: boolean;
    pay_period_id: number | null;
    location: string;
    application_deadline: string;
};

export type CompanyOption = {
    id: number;
    name: string;
    slug: string;
};

export type CatalogueOption = {
    id: number;
    name: string;
    slug: string;
    is_active?: boolean;
};

export type JobCreateProps = {
    companies: CompanyOption[];
    jobCategories: CatalogueOption[];
    employmentTypes: CatalogueOption[];
    workplaceArrangements: CatalogueOption[];
    experienceLevels: CatalogueOption[];
    payPeriods: CatalogueOption[];
};

export type JobEditProps = {
    job: Job;
    companies: CompanyOption[];
    jobCategories: CatalogueOption[];
    employmentTypes: CatalogueOption[];
    workplaceArrangements: CatalogueOption[];
    experienceLevels: CatalogueOption[];
    payPeriods: CatalogueOption[];
};

export type JobFilters = {
    company_id?: number | null;
    status?: string | null;
    search?: string | null;
};

export type JobIndexProps = {
    jobs: {
        data: Job[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    companies: CompanyOption[];
    filters: JobFilters;
};
