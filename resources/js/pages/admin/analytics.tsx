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
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { index as adminAnalyticsIndex } from '@/routes/admin/analytics';
import type { AdminAnalyticsIndexProps } from '@/types';
import {
    formatAnalyticsDate,
    formatAnalyticsPercentage,
    formatAnalyticsWindow,
} from '@/types/analytics';
import type { AnalyticsPreset } from '@/types/analytics';

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
    {
        key: 'users',
        label: 'New users',
        barClassName: 'bg-muted-foreground/70',
    },
];

/**
 * The query payload accepted by the platform analytics index.
 *
 * The server resolves the window itself: an explicit date pair always wins,
 * otherwise the preset is expanded. A preset is therefore sent without
 * dates, and a custom window is sent without a preset.
 */
type AnalyticsFilterParams = {
    preset?: AnalyticsPreset;
    from?: string;
    to?: string;
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

export default function AdminAnalytics({
    window: analyticsWindow,
    summary,
    jobsByStatus,
    applicationsByStatus,
    employersByStatus,
    topCategories,
    trend,
    definitions,
}: AdminAnalyticsIndexProps) {
    const page = usePage<{
        errors: {
            from?: string;
            to?: string;
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
    const [isFiltering, setIsFiltering] = useState(false);

    const isAllTime = analyticsWindow.preset === 'all';
    const hasData =
        summary.totalJobs > 0 ||
        summary.totalApplications > 0 ||
        summary.totalUsers > 0 ||
        summary.totalCompanies > 0;

    const navigate = (params: AnalyticsFilterParams) => {
        router.get(adminAnalyticsIndex.url(), params, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsFiltering(true),
            onFinish: () => setIsFiltering(false),
        });
    };

    const applyPreset = (preset: AnalyticsPreset) => {
        navigate({ preset });
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

        navigate(params);
    };

    const trendEmptyMessage = isAllTime
        ? 'A daily trend is not available for an all-time window.'
        : 'No daily activity was recorded in this window.';

    return (
        <>
            <Head title="Platform Analytics" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Platform Analytics
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Aggregate activity across every company and account.
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
                                ? 'No platform data yet'
                                : 'No activity in this window'
                        }
                        description={
                            isAllTime
                                ? 'The platform has no job postings, applications, or accounts to report on yet.'
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

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                    <StatTile
                        label="Registered users"
                        value={summary.totalUsers}
                        isLoading={isFiltering}
                    />
                    <StatTile
                        label="Companies"
                        value={summary.totalCompanies}
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

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Employers by status
                            </CardTitle>
                            <CardDescription>
                                Employer accounts created inside the window,
                                grouped by their current review status.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <BreakdownBar
                                items={employersByStatus}
                                isLoading={isFiltering}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Top job categories
                            </CardTitle>
                            <CardDescription>
                                Ordered by published postings, then by name. At
                                most five categories are listed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {isFiltering ? (
                                <div className="space-y-2" aria-hidden="true">
                                    <Skeleton className="h-8 w-full" />
                                    <Skeleton className="h-8 w-full" />
                                    <Skeleton className="h-8 w-full" />
                                </div>
                            ) : topCategories.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    No published job postings were created
                                    inside this window.
                                </p>
                            ) : (
                                <ol className="space-y-2">
                                    {topCategories.map((category) => (
                                        <li
                                            key={category.id}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span className="text-foreground">
                                                {category.name}
                                            </span>
                                            <Badge variant="secondary">
                                                {category.publishedJobs}{' '}
                                                published
                                            </Badge>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Daily activity
                        </CardTitle>
                        <CardDescription>
                            New job postings, applications, and accounts per day
                            inside the window.
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

                <AnalyticsDefinitions definitions={definitions} />
            </div>
        </>
    );
}

AdminAnalytics.layout = {
    breadcrumbs: [
        {
            title: 'Platform Analytics',
            href: adminAnalyticsIndex(),
        },
    ],
};
