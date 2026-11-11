import type { PaginatedData } from './authorization';

/**
 * Value unions mirroring the backend enums (see app/Enums/Report*). Keep the
 * members in sync with the PHP enums; labels mirror each enum's label() method.
 */
export type ReportStatusValue =
    | 'pending'
    | 'investigating'
    | 'resolved'
    | 'dismissed';

export type ReportSubjectType = 'job' | 'company';

export type ReportReasonValue =
    | 'scam_or_fraud'
    | 'misleading_listing'
    | 'offensive_content'
    | 'spam'
    | 'other';

export type ReportResolutionActionValue =
    | 'no_action'
    | 'content_removed'
    | 'account_restricted'
    | 'other';

/** Values accepted by the admin queue filters (controller whitelist). */
export type ReportStatusFilterValue = ReportStatusValue | 'all';

export type ReportSubjectFilterValue = ReportSubjectType | 'all';

export const REPORT_REASONS: {
    value: ReportReasonValue;
    label: string;
}[] = [
    { value: 'scam_or_fraud', label: 'Scam or fraud' },
    { value: 'misleading_listing', label: 'Misleading listing' },
    { value: 'offensive_content', label: 'Offensive content' },
    { value: 'spam', label: 'Spam' },
    { value: 'other', label: 'Other' },
];

export const REPORT_STATUSES: {
    value: ReportStatusValue;
    label: string;
}[] = [
    { value: 'pending', label: 'Pending' },
    { value: 'investigating', label: 'Investigating' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'dismissed', label: 'Dismissed' },
];

export const REPORT_RESOLUTION_ACTIONS: {
    value: ReportResolutionActionValue;
    label: string;
}[] = [
    { value: 'no_action', label: 'No action taken' },
    { value: 'content_removed', label: 'Content removed' },
    { value: 'account_restricted', label: 'Account restricted' },
    { value: 'other', label: 'Other' },
];

export const REPORT_SUBJECTS: {
    value: ReportSubjectType;
    label: string;
}[] = [
    { value: 'job', label: 'Job' },
    { value: 'company', label: 'Employer' },
];

/** Look up a human-readable label, falling back to the raw value. */
export function reportReasonLabel(value: ReportReasonValue): string {
    return (
        REPORT_REASONS.find((option) => option.value === value)?.label ?? value
    );
}

export function reportStatusLabel(value: ReportStatusValue): string {
    return (
        REPORT_STATUSES.find((option) => option.value === value)?.label ?? value
    );
}

export function reportResolutionActionLabel(
    value: ReportResolutionActionValue,
): string {
    return (
        REPORT_RESOLUTION_ACTIONS.find((option) => option.value === value)
            ?.label ?? value
    );
}

export function reportSubjectLabel(value: ReportSubjectType): string {
    return (
        REPORT_SUBJECTS.find((option) => option.value === value)?.label ?? value
    );
}

/** Reporter identity shown on the admin queue (never on public surfaces). */
export type ReportReporterSummary = {
    id: number;
    name: string;
};

/** One row of the admin moderation queue (mirrors Admin\ReportController@index). */
export type ReportListItem = {
    id: number;
    subject_type: ReportSubjectType;
    subject_title: string | null;
    subject_status: string | null;
    reason: ReportReasonValue;
    status: ReportStatusValue;
    created_at: string | null;
    reporter: ReportReporterSummary;
};

export type AdminReportsIndexProps = {
    reports: PaginatedData<ReportListItem>;
    filters: {
        status: ReportStatusFilterValue;
        subject: ReportSubjectFilterValue;
        reason: ReportReasonValue | 'all';
    };
};

export type ReportSubjectPerson = {
    id: number;
    name: string;
    email: string;
};

/** Subject payload for a job report (mirrors Admin\ReportController@show). */
export type ReportJobSubject = {
    type: 'job';
    id: number;
    title: string;
    status: string | null;
    company: {
        id: number;
        name: string;
        is_active: boolean;
        owner: ReportSubjectPerson | null;
    } | null;
};

/** Subject payload for a company report (mirrors Admin\ReportController@show). */
export type ReportCompanySubject = {
    type: 'company';
    id: number | null;
    title: string | null;
    status: 'active' | 'inactive' | null;
    owner: ReportSubjectPerson | null;
};

export type ReportDetailSubject = ReportJobSubject | ReportCompanySubject;

/** Full report payload for the admin detail page (mirrors Admin\ReportController@show). */
export type ReportDetail = {
    id: number;
    status: ReportStatusValue;
    reason: ReportReasonValue;
    explanation: string | null;
    resolution_action: ReportResolutionActionValue | null;
    resolution_message: string | null;
    internal_note: string | null;
    resolved_at: string | null;
    created_at: string | null;
    reporter: {
        id: number;
        name: string;
        email: string;
    };
    subject: ReportDetailSubject;
};

export type AdminReportShowProps = {
    report: ReportDetail;
};

/** Form shape for the public report dialog. */
export type ReportDialogForm = {
    subject: ReportSubjectType;
    reason: string;
    explanation: string;
};

export type ReportDialogProps = {
    job: {
        id: number;
        title: string;
        company: { id: number; name: string } | null;
    };
    /**
     * Optional custom trigger element. When omitted a subtle secondary
     * "Report" button is rendered.
     */
    children?: React.ReactNode;
};

/** Form shape for the admin resolve action. */
export type ResolveReportForm = {
    resolution_action: string;
    resolution_message: string;
    internal_note: string;
};

/** Form shape for the admin dismiss action. */
export type DismissReportForm = {
    internal_note: string;
};
