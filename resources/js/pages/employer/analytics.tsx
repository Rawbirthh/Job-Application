import { Head, router, usePage } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import AnalyticsDefinitions from '@/components/analytics/analytics-definitions';
import AnalyticsEmpty from '@/components/analytics/analytics-empty';
import BreakdownBar from '@/components/analytics/breakdown-bar';
import StatTile from '@/components/analytics/stat-tile';
import TrendChart, {
    type TrendSeries,
} from '@/components/analytics/trend-chart';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { index as employerAnalyticsIndex } from '@/routes/employer/analytics';
import type { EmployerAnalyticsIndexProps } from '@/types';
import {
    formatAnalyticsDate,
    formatAnalyticsPercentage,
    formatAnalyticsTimestamp,
    formatAnalyticsWindow,
} from '@/types/analytics';
import type { AnalyticsPreset } from '@/types/analytics';

const ALL_COMPANIES = 'all';

/** The window presets offered by the filter bar. */
const PRESET_OPTIONS: Array<{ value: AnalyticsPreset; label: string }> = [
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: 'all', label: 'All time' },
];

/** The series plotted by the daily trend chart. */
const TREND_SERIES: TrendSeries[] = [
    { key: 'jobs', label: 'New jobs', barClassName: 'bg-primary' },
    {
        key: 'applications',
        label: 'New applications',
        barClassName: 'bg-teal-600 dark:bg-teal-400',
    },
];

/**
 * The query payload accepted by the employer analytics index.
 *
 * The server resolves the window itself: an explicit date pair always wins,
 * otherwise the preset is expanded. A preset is therefore sent without
 * dates, and a custom window is sent without a preset.
 */
type AnalyticsFilterParams = {
    preset?: AnalyticsPreset;
    from?: string;
    to?: string;
    company_id?: number;
};

/**
 * Read one bound of the current window from the URL.
 *
 * The URL keeps the window the reader asked for, so a rejected date stays
 * visible next to its error message. A value that is not a calendar date is
 * ignored: the browser date input cannot produce one.
 */
function readDateParam(url: string, key: string): string | null {
    const separator = url.indexOf('?');

    if (separator === -1) {
        return null;
    }

    const value = new URLSearchParams(url.slice(separator + 1)).get(key);

    return value !== null && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null;
}

/** Map a job status to the badge variant used by the table. */
function jobStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'published':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'rejected':
            return 'destructive';
        default:
            return 'outline';
    }
}

export default function EmployerAnalytics({
    window: analyticsWindow,
    companies,
    selectedCompanyId,
    summary,
    jobsByStatus,
    applicationsByStatus,
    trend,
    jobs,
    definitions,
}: EmployerAnalyticsIndexProps) {
    const page = usePage<{
        errors: {
            from?: string;
            to?: string;
            company_id?: string;
        };
    }>();

    const { errors } = page.props;
    const { url } = page;

    const [from, setFrom] = useState(
        readDateParam(url, 'from') ?? analyticsWindow.from ?? '',
    );
    const [to, setTo] = useState(
        readDateParam(url, 'to') ?? analyticsWindow.to ?? '',
    );
    const [companyId, setCompanyId] = useState(
        selectedCompanyId !== null ? String(selectedCompanyId) : ALL_COMPANIES,
    );
    const [isFiltering, setIsFiltering] = useState(false);

    const isAllTime = analyticsWindow.preset === 'all';
    const hasData = summary.totalJobs > 0 || summary.totalApplications > 0;

    const navigate = (params: AnalyticsFilterParams) => {
        router.get(employerAnalyticsIndex.url(), params, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsFiltering(true),
            onFinish: () => setIsFiltering(false),
        });
    };

    const applyPreset = (preset: AnalyticsPreset) => {
        const params: AnalyticsFilterParams = { preset };

        if (companyId !== ALL_COMPANIES) {
            params.company_id = Number(companyId);
        }

        navigate(params);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const params: AnalyticsFilterParams = {};
        const trimmedFrom = from.trim();
        const trimmedTo = to.trim();

        if (trimmedFrom !== '' || trimmedTo !== '') {
            if (trimmedFrom !== '') {
                params.from = trimmedFrom;
            }

            if (trimmedTo !== '') {
                params.to = trimmedTo;
            }
        } else {
            // An empty date pair means "no date restriction", which is the
            // unbounded window the server models with the `all` preset.
            params.preset = 'all';
        }

        if (companyId !== ALL_COMPANIES) {
            params.company_id = Number(companyId);
        }

        navigate(params);
    };

    const trendEmptyMessage = isAllTime
        ? 'A daily trend is not available for an all-time window.'
        : 'No daily activity was recorded in this window.';

    return (
        <>
            <Head title="Employer Analytics" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Employer Analytics
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        See how your job postings and hiring pipeline perform.
                        Every metric counts the records created inside the
                        selected window.
                    </p>
                </div>

                <form
                    onSubmit={handleSubmit}
                    aria-busy={isFiltering}
                    className="space-y-4"
                >
                    <fieldset>
                        <legend className="mb-2 text-sm font-medium text-foreground">
                            Window
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {PRESET_OPTIONS.map((option) => {
                                const isActive =
                                    analyticsWindow.preset === option.value;

                                return (
                                    <Button
                                        key={option.value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            isActive ? 'default' : 'outline'
                                        }
                                        aria-pressed={isActive}
                                        disabled={isFiltering}
                                        onClick={() =>
                                            applyPreset(option.value)
                                        }
                                    >
                                        {option.label}
                                    </Button>
                                );
                            })}
                        </div>
                    </fieldset>

                    <div className="flex flex-wrap items-end gap-4">
                        <div className="w-full sm:w-56">
                            <Label htmlFor="analytics-company">Company</Label>
                            <Select
                                value={companyId}
                                onValueChange={setCompanyId}
                            >
                                <SelectTrigger
                                    id="analytics-company"
                                    className="w-full"
                                    aria-invalid={
                                        errors.company_id ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.company_id
                                            ? 'analytics-company-error'
                                            : undefined
                                    }
                                >
                                    <SelectValue placeholder="All companies" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_COMPANIES}>
                                        All companies
                                    </SelectItem>
                                    {companies.map((company) => (
                                        <SelectItem
                                            key={company.id}
                                            value={String(company.id)}
                                        >
                                            {company.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                id="analytics-company-error"
                                message={errors.company_id}
                                className="mt-2"
                            />
                        </div>

                        <div className="w-full sm:w-44">
                            <Label htmlFor="analytics-from">From</Label>
                            <Input
                                id="analytics-from"
                                name="from"
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                aria-invalid={errors.from ? true : undefined}
                                aria-describedby={
                                    errors.from
                                        ? 'analytics-from-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="analytics-from-error"
                                message={errors.from}
                                className="mt-2"
                            />
                        </div>

                        <div className="w-full sm:w-44">
                            <Label htmlFor="analytics-to">To</Label>
                            <Input
                                id="analytics-to"
                                name="to"
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                                aria-invalid={errors.to ? true : undefined}
                                aria-describedby={
                                    errors.to ? 'analytics-to-error' : undefined
                                }
                            />
                            <InputError
                                id="analytics-to-error"
                                message={errors.to}
                                className="mt-2"
                            />
                        </div>

                        <Button type="submit" disabled={isFiltering}>
                            {isFiltering ? (
                                <Spinner />
                            ) : (
                                <Filter aria-hidden="true" />
                            )}
                            Apply
                        </Button>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Showing {formatAnalyticsWindow(analyticsWindow)}.
                        Records are counted by the day they were created.
                    </p>
                </form>

                {!hasData && !isFiltering ? (
                    <AnalyticsEmpty
                        title={
                            isAllTime
                                ? 'No analytics data yet'
                                : 'No activity in this window'
                        }
                        description={
                            isAllTime
                                ? 'None of your companies has a job posting or an application yet. This page fills in as soon as they do.'
                                : `Nothing was created between ${formatAnalyticsDate(
                                      analyticsWindow.from,
                                  )} and ${formatAnalyticsDate(
                                      analyticsWindow.to,
                                  )}.`
                        }
                        actionLabel={isAllTime ? null : 'Show all time'}
                        onAction={isAllTime ? null : () => applyPreset('all')}
                        isLoading={isFiltering}
                    />
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <StatTile
                        label="Total jobs"
                        value={summary.totalJobs}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Published jobs"
                        value={summary.publishedJobs}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Total applications"
                        value={summary.totalApplications}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Advanced (shortlist or beyond)"
                        value={summary.advancedApplications}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Conversion rate"
                        value={formatAnalyticsPercentage(
                            summary.conversionRate,
                        )}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Hires"
                        value={summary.hires}
                        isLoading={isFiltering}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Jobs by status
                            </CardTitle>
                            <CardDescription>
                                Job postings created inside the window, grouped
                                by their current status.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BreakdownBar
                                items={jobsByStatus}
                                isLoading={isFiltering}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Applications by status
                            </CardTitle>
                            <CardDescription>
                                Applications submitted inside the window,
                                grouped by their current status.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BreakdownBar
                                items={applicationsByStatus}
                                isLoading={isFiltering}
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Daily activity
                        </CardTitle>
                        <CardDescription>
                            New job postings and applications per day inside the
                            window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TrendChart
                            points={trend}
                            series={TREND_SERIES}
                            isLoading={isFiltering}
                            emptyMessage={trendEmptyMessage}
                        />
                    </CardContent>
                </Card>

                {hasData ? (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Job performance
                            </CardTitle>
                            <CardDescription>
                                Applications, shortlists, and conversion rate
                                per job posting created inside the window. An
                                application to a job created before the window
                                still counts towards the totals above.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {isFiltering ? (
                                <div className="space-y-2" aria-hidden="true">
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-lg border border-border">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">
                                            Job postings created inside the
                                            selected window
                                        </caption>
                                        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Job
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Company
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Created
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right"
                                                >
                                                    Applications
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right"
                                                >
                                                    Advanced
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right"
                                                >
                                                    Conversion
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {jobs.data.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={7}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        No job postings were
                                                        created inside this
                                                        window.
                                                    </td>
                                                </tr>
                                            ) : (
                                                jobs.data.map((job) => (
                                                    <tr
                                                        key={job.id}
                                                        className="hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-3 font-medium text-foreground">
                                                            {job.title}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={jobStatusVariant(
                                                                    job.status,
                                                                )}
                                                            >
                                                                {job.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {job.company
                                                                ?.name ?? '—'}
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {formatAnalyticsTimestamp(
                                                                job.created_at,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {
                                                                job.applications_count
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {job.advanced_count}
                                                        </td>
                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {formatAnalyticsPercentage(
                                                                job.conversion_rate,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {jobs.total > 0 ? (
                                <Pagination
                                    currentPage={jobs.current_page}
                                    lastPage={jobs.last_page}
                                    perPage={jobs.per_page}
                                    total={jobs.total}
                                    prevPageUrl={jobs.prev_page_url}
                                    nextPageUrl={jobs.next_page_url}
                                />
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                <AnalyticsDefinitions definitions={definitions} />
            </div>
        </>
    );
}

EmployerAnalytics.layout = {
    breadcrumbs: [
        {
            title: 'Employer Analytics',
            href: employerAnalyticsIndex(),
        },
    ],
};
