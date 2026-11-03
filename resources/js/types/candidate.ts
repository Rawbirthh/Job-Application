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
