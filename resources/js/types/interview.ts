export type InterviewStatus =
    | 'scheduled'
    | 'accepted'
    | 'declined'
    | 'reschedule_requested'
    | 'cancelled';

export type InterviewMode = 'online' | 'onsite' | 'phone';

/**
 * Candidate-safe interview round payload shared by both application detail
 * pages. Employer-only fields are added by {@link EmployerInterviewRound}.
 */
export type InterviewRound = {
    id: number;
    status: InterviewStatus;
    status_label: string;
    mode: InterviewMode;
    mode_label: string;
    /** ISO-8601 UTC instant. */
    scheduled_at: string;
    /** IANA time zone selected by the employer for display. */
    timezone: string;
    duration_minutes: number;
    location_details: string;
    candidate_instructions: string | null;
    responded_at: string | null;
    requested_scheduled_at: string | null;
    requested_timezone: string | null;
    response_note: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    updated_at: string;
};

export type EmployerInterviewRound = InterviewRound & {
    internal_notes: string | null;
    can_manage: boolean;
};

export type CandidateInterviewRound = InterviewRound & {
    can_respond: boolean;
};

export type InterviewPagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type InterviewScheduleFormData = {
    /** Wall-clock value for a `datetime-local` input in `timezone`. */
    scheduled_at_local: string;
    timezone: string;
    /** Kept as a string because it mirrors the `datetime-local`/number input value. */
    duration_minutes: string;
    mode: InterviewMode;
    location_details: string;
    candidate_instructions: string;
    internal_notes: string;
};

export type InterviewRescheduleFormData = InterviewScheduleFormData & {
    current_status: InterviewStatus;
    current_scheduled_at: string;
};

export type InterviewCancelFormData = {
    current_status: InterviewStatus;
    current_scheduled_at: string;
    cancellation_reason: string;
};

export type InterviewResponseValue =
    | 'accepted'
    | 'declined'
    | 'reschedule_requested';

export type InterviewDecisionFormData = {
    response: 'accepted' | 'declined';
    current_status: InterviewStatus;
    current_scheduled_at: string;
};

export type InterviewRescheduleRequestFormData = {
    response: 'reschedule_requested';
    current_status: InterviewStatus;
    current_scheduled_at: string;
    /** Wall-clock value for a `datetime-local` input in `requested_timezone`. */
    requested_at_local: string;
    requested_timezone: string;
    response_note: string;
};
