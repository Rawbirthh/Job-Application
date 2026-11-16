/**
 * Shared types for the visual recruitment pipeline board (Feature 37).
 *
 * The board is a read surface over the existing application status workflow:
 * every value below is server owned. The client never decides which status
 * transitions are legal — the allowed targets arrive in `transition_matrix`.
 */

export type PipelineStatus =
    | 'submitted'
    | 'reviewing'
    | 'shortlisted'
    | 'interview_scheduled'
    | 'offer_sent'
    | 'hired'
    | 'rejected'
    | 'withdrawn';

export type PipelineCompany = {
    id: number;
    name: string;
};

export type PipelineJobOption = {
    id: number;
    title: string;
    count: number;
};

export type PipelineCard = {
    id: number;
    status: PipelineStatus;
    applied_at: string | null;
    candidate: {
        id: number;
        name: string;
        headline: string | null;
        location: string | null;
    };
    job: {
        id: number;
        title: string;
        status: string;
    };
};

export type PipelineColumn = {
    status: PipelineStatus;
    label: string;
    count: number;
    shown: number;
    has_more: boolean;
    items: PipelineCard[];
};

export type PipelineBoard = {
    company: PipelineCompany | null;
    companies: PipelineCompany[];
    jobs: PipelineJobOption[];
    filters: {
        company_id: number | null;
        job_id: number | null;
        search: string | null;
    };
    status_counts: Record<PipelineStatus, number>;
    columns: PipelineColumn[];
    can_move: boolean;
    transition_matrix: Record<PipelineStatus, PipelineStatus[]>;
    status_labels: Record<PipelineStatus, string>;
};

/**
 * The per-column card limits carried in the `limit[<status>]` query parameter.
 */
export type PipelineColumnLimits = Partial<Record<PipelineStatus, number>>;
