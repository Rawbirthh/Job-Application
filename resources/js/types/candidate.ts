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

export type JobApplicationForm = {
    resume_id: number;
    cover_letter?: string;
};

export type ApplicationStatus = 'submitted' | 'reviewing' | 'shortlisted' | 'interview_scheduled' | 'offer_sent' | 'hired' | 'rejected' | 'withdrawn';

export type ApplicationIndexProps = {
    applications: Application[];
    currentPage?: number;
    lastPage?: number;
    perPage?: number;
    total?: number;
    prevPageUrl?: string | null;
    nextPageUrl?: string | null;
};

export type Application = {
    id: number;
    job_id: number;
    company_name: string | null;
    job_title: string;
    status: ApplicationStatus;
    applied_at: string | null;
    cover_letter?: string | null;
    resume: CandidateResumeData | null;
};

export type ApplicationIndexFormProps = {
    application: Application;
};

export type JobDetail = {
    id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    location: string | null;
    salary_min: string | null;
    salary_max: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    application_deadline: string | null;
    published_at: string | null;
    is_saved?: boolean;
    isSaved?: boolean;
    has_applied?: boolean;
    company: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
        overview: string | null;
        website: string | null;
        location: string | null;
        industry: string | null;
        size_band: string | null;
    } | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    pay_period: { id: number; name: string } | null;
};

export type JobDetailPageProps = {
    job: JobDetail;
    auth: { user?: { id: number; name?: string; email?: string; roles: string[] }; employerStatus?: string };
    application: {
        exists: boolean;
        alreadyApplied: boolean;
    };
};