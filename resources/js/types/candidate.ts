export type CandidateEducation = {
    id?: number;
    institution: string;
    degree: string | null;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
};

export type CandidateExperience = {
    id?: number;
    company_name: string;
    job_title: string;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    description: string | null;
};

export type CandidateProfile = {
    headline: string | null;
    summary: string | null;
    location: string | null;
    skills: string[];
    educations: CandidateEducation[];
    experiences: CandidateExperience[];
    updated_at: string | null;
};

export type CandidateProfileForm = {
    headline: string | null;
    summary: string | null;
    location: string | null;
    skills: string[];
    educations: CandidateEducation[];
    experiences: CandidateExperience[];
};

export type CandidateProfileEditProps = {
    profile: CandidateProfile;
};

export type CandidateResumeData = {
    id: number;
    original_filename: string;
    mime_type: string;
    size: number;
    created_at: string | null;
    updated_at: string | null;
};

export type ResumeIndexProps = {
    resume: CandidateResumeData | null;
};

export type SavedJobItem = {
    id: number;
    jobPostingId?: number;
    job_posting_id?: number;
    title: string;
    companyName: string;
    company_name?: string;
    location: string | null;
    employmentType: string | null;
    employment_type?: string | null;
    workplaceArrangement: string | null;
    workplace_arrangement?: string | null;
    salarySummary: string | null;
    salary_summary?: string | null;
    publishedAt?: string | null;
    published_at?: string | null;
    updatedAt?: string | null;
    updated_at?: string | null;
    status?: string;
    is_closed?: boolean;
    isClosed?: boolean;
};

export type SavedJobsPaginated = {
    data: SavedJobItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type SavedJobsIndexProps = {
    savedJobs: SavedJobItem[] | SavedJobsPaginated;
    currentPage?: number;
    lastPage?: number;
    perPage?: number;
    total?: number;
    prevPageUrl?: string | null;
    nextPageUrl?: string | null;
};
