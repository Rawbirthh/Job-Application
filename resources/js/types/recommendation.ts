import type { PaginatedData } from './authorization';

export type RecommendationReasonCode =
    | 'skill_overlap'
    | 'category_match'
    | 'experience_level_match'
    | 'location_match'
    | 'workplace_match';

export type RecommendationReason = {
    code: RecommendationReasonCode;
    label: string;
    detail: string;
    values: string[];
};

export type RecommendedJob = {
    id: number;
    title: string;
    location: string | null;
    salary_min: number | null;
    salary_max: number | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    application_deadline: string | null;
    published_at: string | null;
    company: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
    } | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    match_score: number | null;
    reasons: RecommendationReason[];
    has_applied: boolean;
    is_saved: boolean;
};

export type RecommendationMode = 'matched' | 'fallback';

export type RecommendationProfileSummary = {
    has_skills: boolean;
    has_category: boolean;
    has_experience_level: boolean;
    has_location: boolean;
    has_workplace: boolean;
    signals_available: number;
    computed_years: number | null;
};

export type RecommendationIndexProps = {
    recommendations: PaginatedData<RecommendedJob>;
    mode: RecommendationMode;
    profileSummary: RecommendationProfileSummary;
};
