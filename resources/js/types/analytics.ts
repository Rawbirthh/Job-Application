import type { PaginatedData } from './authorization';

/**
 * The window presets offered by the analytics filters.
 *
 * Mirrors the server-side whitelist in
 * App\Http\Requests\Concerns\ResolvesAnalyticsWindow.
 */
export type AnalyticsPreset = 'all' | '7d' | '30d' | '90d';

/**
 * The resolved window of a rendered page.
 *
 * `custom` means the reader supplied an explicit `from`/`to` pair. An
 * explicit date pair always wins over a preset on the server.
 */
export type AnalyticsWindowPreset = AnalyticsPreset | 'custom';

/** The effective analytics window as resolved by the server. */
export type AnalyticsWindow = {
    /** An inclusive `Y-m-d` calendar date, or null for an open bound. */
    from: string | null;
    /** An inclusive `Y-m-d` calendar date, or null for an open bound. */
    to: string | null;
    preset: AnalyticsWindowPreset;
};

/**
 * One row of a status breakdown.
 *
 * The label is produced by the server from the backend enums, so the
 * frontend never has to mirror the status vocabulary.
 */
export type AnalyticsStatusCount = {
    status: string;
    label: string;
    count: number;
};

/** The headline metrics shared by both analytics pages. */
export type AnalyticsSummary = {
    totalJobs: number;
    publishedJobs: number;
    totalApplications: number;
    advancedApplications: number;
    /** A ratio between 0 and 1, rounded to four decimal places. */
    conversionRate: number;
    hires: number;
};

/** The platform summary adds the account totals. */
export type PlatformAnalyticsSummary = AnalyticsSummary & {
    totalUsers: number;
    totalCompanies: number;
};

/** One day of the daily trend. */
export type AnalyticsTrendPoint = {
    /** A `Y-m-d` calendar date. */
    date: string;
    jobs: number;
    applications: number;
};

/** The platform trend adds the daily new-account count. */
export type PlatformAnalyticsTrendPoint = AnalyticsTrendPoint & {
    users: number;
};

/** A company the signed-in employer may read analytics for. */
export type AnalyticsCompanyOption = {
    id: number;
    name: string;
    slug: string;
};

/** One row of the per-job performance table. */
export type AnalyticsJobRow = {
    id: number;
    title: string;
    status: string;
    company: { id: number; name: string } | null;
    /** An ISO 8601 timestamp, or null for a job without a creation date. */
    created_at: string | null;
    applications_count: number;
    advanced_count: number;
    /** A ratio between 0 and 1, rounded to four decimal places. */
    conversion_rate: number;
};

/** One job category of the platform top-categories card. */
export type AnalyticsCategoryCount = {
    id: number;
    name: string;
    publishedJobs: number;
};

/** One metric definition rendered by the disclosure component. */
export type AnalyticsDefinition = {
    id: string;
    label: string;
    description: string;
};

/** The props of the employer analytics page. */
export type EmployerAnalyticsIndexProps = {
    window: AnalyticsWindow;
    companies: AnalyticsCompanyOption[];
    /** The company the metrics are scoped to, or null for all companies. */
    selectedCompanyId: number | null;
    summary: AnalyticsSummary;
    jobsByStatus: AnalyticsStatusCount[];
    applicationsByStatus: AnalyticsStatusCount[];
    /** Empty when the window is unbounded. */
    trend: AnalyticsTrendPoint[];
    jobs: PaginatedData<AnalyticsJobRow>;
    definitions: AnalyticsDefinition[];
};

/** The props of the platform analytics page. */
export type AdminAnalyticsIndexProps = {
    window: AnalyticsWindow;
    summary: PlatformAnalyticsSummary;
    jobsByStatus: AnalyticsStatusCount[];
    applicationsByStatus: AnalyticsStatusCount[];
    employersByStatus: AnalyticsStatusCount[];
    topCategories: AnalyticsCategoryCount[];
    /** Empty when the window is unbounded. */
    trend: PlatformAnalyticsTrendPoint[];
    definitions: AnalyticsDefinition[];
};

/**
 * Format a ratio between 0 and 1 as a percentage with one decimal.
 *
 * The runtime helpers below live next to the analytics types, mirroring
 * `types/audit-log.ts`. They are imported directly from `@/types/analytics`
 * because the `@/types` barrel only re-exports types.
 */
export function formatAnalyticsPercentage(rate: number): string {
    return `${(rate * 100).toFixed(1)}%`;
}

/**
 * Format a `Y-m-d` calendar date for display.
 *
 * The analytics window is computed in UTC, so the calendar day is rendered
 * in UTC too; otherwise a reader in another time zone would see the wrong
 * day for the window they selected.
 */
export function formatAnalyticsDate(value: string | null): string {
    if (value === null || value.trim() === '') {
        return '—';
    }

    const parsed = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

/** Format an ISO 8601 timestamp as its UTC calendar day. */
export function formatAnalyticsTimestamp(value: string | null): string {
    if (value === null || value.trim() === '') {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

/** Describe the effective window in a single human-readable phrase. */
export function formatAnalyticsWindow(window: AnalyticsWindow): string {
    if (window.preset === 'all') {
        return 'All time';
    }

    // A window may have only one bound, so an open edge is named rather than
    // rendered as an empty value.
    if (window.from === null) {
        return `Until ${formatAnalyticsDate(window.to)}`;
    }

    if (window.to === null) {
        return `From ${formatAnalyticsDate(window.from)} onwards`;
    }

    return `${formatAnalyticsDate(window.from)} – ${formatAnalyticsDate(
        window.to,
    )}`;
}
