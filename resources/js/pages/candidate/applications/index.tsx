import { Head, Link, router } from '@inertiajs/react';
import { Bookmark, Building2, Briefcase, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import Pagination from '@/components/pagination';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';

type ApplicationFilters = {
    status: string | null;
};

interface Props {
    applications: { data: any[] };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: ApplicationFilters;
    statusCounts: Record<string, number>;
}

const STATUS_ORDER = [
    'submitted',
    'reviewing',
    'shortlisted',
    'interview_scheduled',
    'offer_sent',
    'hired',
    'rejected',
    'withdrawn',
] as const;

type ApplicationStatusValue = (typeof STATUS_ORDER)[number];

const STATUS_LABELS: Record<ApplicationStatusValue, string> = {
    submitted: 'Submitted',
    reviewing: 'Reviewing',
    shortlisted: 'Shortlisted',
    interview_scheduled: 'Interview Scheduled',
    offer_sent: 'Offer Sent',
    hired: 'Hired',
    rejected: 'Rejected',
    withdrawn: 'Withdrawn',
};

const STATUS_STYLES: Record<ApplicationStatusValue, string> = {
    submitted: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    reviewing:
        'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400',
    shortlisted:
        'border-teal-500/40 bg-teal-500/10 text-teal-700 dark:text-teal-400',
    interview_scheduled:
        'border-violet-500/40 bg-violet-500/10 text-violet-700 dark:text-violet-400',
    offer_sent:
        'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    hired: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    rejected: 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-400',
    withdrawn: 'border-border bg-muted text-muted-foreground',
};

const STATUS_DOT: Record<ApplicationStatusValue, string> = {
    submitted: 'bg-sky-500',
    reviewing: 'bg-blue-500',
    shortlisted: 'bg-teal-500',
    interview_scheduled: 'bg-violet-500',
    offer_sent: 'bg-amber-500',
    hired: 'bg-emerald-500',
    rejected: 'bg-red-500',
    withdrawn: 'bg-muted-foreground',
};

function statusStyle(status: string): string {
    return (
        STATUS_STYLES[status as ApplicationStatusValue] ??
        'border-border bg-muted text-muted-foreground'
    );
}

function statusLabel(status: string): string {
    return (
        STATUS_LABELS[status as ApplicationStatusValue] ??
        status.replaceAll('_', ' ')
    );
}

export default function CandidateApplicationsIndex({
    applications,
    pagination,
    filters,
    statusCounts,
}: Props) {
    useFlashToast();

    const activeStatus = filters?.status ?? null;
    const totalAcrossStatuses = Object.values(statusCounts ?? {}).reduce(
        (sum, count) => sum + count,
        0,
    );

    const applyFilter = (status: ApplicationStatusValue | null) => {
        router.get('/candidate/applications', status ? { status } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const items = applications.data;

    return (
        <div className="flex w-full flex-1 flex-col gap-6 p-6 md:p-8">
            <Head title="My Applications" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        My Applications
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground md:text-base">
                        Track your job applications and their current status ·{' '}
                        {pagination.total}{' '}
                        {pagination.total === 1
                            ? 'application'
                            : 'applications'}
                        {activeStatus &&
                            ` in ${statusLabel(activeStatus).toLowerCase()}`}
                    </p>
                </div>

                <Button asChild variant="outline" size="sm">
                    <Link href="/jobs">Browse Jobs</Link>
                </Button>
            </div>

            {/* Status filter: chip toggles, not a dropdown */}
            <div
                role="group"
                aria-label="Filter by application status"
                className="flex gap-2 overflow-x-auto pb-1"
            >
                <button
                    type="button"
                    onClick={() => applyFilter(null)}
                    aria-pressed={activeStatus === null}
                    className={cn(
                        'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        activeStatus === null
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                    )}
                >
                    All · {totalAcrossStatuses}
                </button>
                {STATUS_ORDER.map((status) => {
                    const count = statusCounts?.[status] ?? 0;
                    const isActive = activeStatus === status;

                    return (
                        <button
                            key={status}
                            type="button"
                            onClick={() =>
                                applyFilter(isActive ? null : status)
                            }
                            aria-pressed={isActive}
                            className={cn(
                                'flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                isActive
                                    ? statusStyle(status)
                                    : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'size-1.5 rounded-full',
                                    STATUS_DOT[status],
                                )}
                            />
                            {STATUS_LABELS[status]} · {count}
                        </button>
                    );
                })}
            </div>

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Bookmark className="h-8 w-8 text-primary" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            {activeStatus
                                ? `No ${statusLabel(activeStatus).toLowerCase()} applications`
                                : 'No applications yet'}
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            {activeStatus
                                ? 'Try a different status filter.'
                                : "You haven't applied to any jobs yet. Browse jobs and apply to start tracking your applications."}
                        </p>
                        {activeStatus ? (
                            <Button
                                className="mt-2"
                                onClick={() => applyFilter(null)}
                            >
                                View all applications
                            </Button>
                        ) : (
                            <Button asChild className="mt-2">
                                <Link href="/jobs">Browse Job Listings</Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {items.map((application: any) => {
                        const companyName: string | null =
                            application.company?.name ?? null;
                        const initial = companyName
                            ? companyName.trim().charAt(0).toUpperCase()
                            : '?';
                        const salaryMin =
                            application.salary_min !== null &&
                            application.salary_min !== ''
                                ? Number(application.salary_min)
                                : null;
                        const salaryMax =
                            application.salary_max !== null &&
                            application.salary_max !== ''
                                ? Number(application.salary_max)
                                : null;
                        const salaryText =
                            salaryMin !== null || salaryMax !== null
                                ? `${application.salary_currency ?? ''} ${
                                      salaryMin !== null
                                          ? salaryMin.toLocaleString()
                                          : '—'
                                  } - ${
                                      salaryMax !== null
                                          ? salaryMax.toLocaleString()
                                          : '—'
                                  }${
                                      application.salary_negotiable
                                          ? ' · Negotiable'
                                          : ''
                                  }`.trim()
                                : null;

                        return (
                            <Card
                                key={application.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-3 p-4">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex min-w-0 items-start gap-4 sm:items-center">
                                            <div
                                                aria-hidden="true"
                                                className="hidden size-12 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-lg font-semibold text-muted-foreground sm:flex"
                                            >
                                                {initial}
                                            </div>
                                            <div className="min-w-0 space-y-1.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="text-base leading-snug font-semibold sm:text-lg">
                                                        <Link
                                                            href={`/jobs/${application.job_id}`}
                                                            className="text-primary hover:underline"
                                                        >
                                                            {
                                                                application.job_title
                                                            }
                                                        </Link>
                                                    </h2>
                                                    {application.job_status ===
                                                        'closed' && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-red-500/40 bg-red-500/10 text-xs text-red-700 dark:text-red-400"
                                                        >
                                                            Job Closed
                                                        </Badge>
                                                    )}
                                                </div>

                                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground sm:text-sm">
                                                    {companyName && (
                                                        <span className="flex items-center gap-1 font-medium text-foreground">
                                                            <Building2 className="h-4 w-4" />
                                                            {companyName}
                                                        </span>
                                                    )}
                                                    {application.location && (
                                                        <span className="flex items-center gap-1">
                                                            <MapPin className="h-4 w-4" />
                                                            {
                                                                application.location
                                                            }
                                                        </span>
                                                    )}
                                                    {application.employment_type && (
                                                        <span className="flex items-center gap-1">
                                                            <Briefcase className="h-4 w-4" />
                                                            {
                                                                application.employment_type
                                                            }
                                                        </span>
                                                    )}
                                                    {salaryText && (
                                                        <span className="font-medium text-foreground">
                                                            {salaryText}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                'w-fit shrink-0',
                                                statusStyle(application.status),
                                            )}
                                        >
                                            {statusLabel(application.status)}
                                        </Badge>
                                    </div>

                                    <div className="flex items-center justify-between gap-2 border-t border-border pt-3">
                                        <span className="text-xs text-muted-foreground">
                                            Applied on{' '}
                                            {application.applied_at
                                                ? new Date(
                                                      application.applied_at,
                                                  ).toLocaleDateString(
                                                      'en-US',
                                                      {
                                                          year: 'numeric',
                                                          month: 'long',
                                                          day: 'numeric',
                                                      },
                                                  )
                                                : 'N/A'}
                                        </span>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={`/candidate/applications/${application.id}`}
                                            >
                                                View Details
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {pagination.last_page > 1 && (
                        <div className="mt-2">
                            <Pagination
                                currentPage={pagination.current_page}
                                lastPage={pagination.last_page}
                                perPage={pagination.per_page}
                                total={pagination.total}
                                prevPageUrl={pagination.prev_page_url}
                                nextPageUrl={pagination.next_page_url}
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
