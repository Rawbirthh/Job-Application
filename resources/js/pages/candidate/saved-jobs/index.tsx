import { Head, Link, router } from '@inertiajs/react';
import {
    Bookmark,
    Briefcase,
    Building2,
    ExternalLink,
    MapPin,
    Trash2,
} from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { SavedJobsIndexProps } from '@/types/candidate';

export default function SavedJobsIndex({ savedJobs }: SavedJobsIndexProps) {
    useFlashToast();

    const items = Array.isArray(savedJobs) ? savedJobs : savedJobs.data;
    const pagination = !Array.isArray(savedJobs) ? savedJobs : null;
    const total = pagination?.total ?? items.length;

    const handleUnsave = (jobId: number) => {
        router.delete(`/candidate/saved-jobs/${jobId}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex w-full flex-1 flex-col gap-6 p-6 md:p-8">
            <Head title="Saved Jobs" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Saved Jobs
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground md:text-base">
                        Your bookmarked jobs · {total}{' '}
                        {total === 1 ? 'saved job' : 'saved jobs'}
                    </p>
                </div>
                <Button asChild variant="outline" size="sm">
                    <Link href="/jobs">Browse Jobs</Link>
                </Button>
            </div>

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Bookmark className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            No saved jobs yet
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            You haven't saved any jobs. Browse published job
                            listings and click the save button to bookmark jobs
                            for later.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href="/jobs">Browse Job Listings</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {items.map((saved: any) => {
                        // The payload tolerates both flat items and a nested
                        // job object (snake_case and camelCase keys).
                        const job = saved.job || saved;
                        const jobId = job?.id || saved.job_posting_id;
                        const companyName: string | null =
                            job?.companyName ??
                            job?.company_name ??
                            job?.company?.name ??
                            null;
                        const employmentType: string | null =
                            job?.employmentType ??
                            job?.employment_type?.name ??
                            (typeof job?.employment_type === 'string'
                                ? job.employment_type
                                : null);
                        const salarySummary: string | null =
                            job?.salarySummary ?? job?.salary_summary ?? null;
                        const isClosed =
                            job?.status === 'closed' ||
                            job?.is_closed === true ||
                            job?.isClosed === true;
                        const initial = companyName
                            ? companyName.trim().charAt(0).toUpperCase()
                            : '?';

                        return (
                            <Card
                                key={saved.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                                    <div
                                        aria-hidden="true"
                                        className="hidden size-12 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-lg font-semibold text-muted-foreground sm:flex"
                                    >
                                        {initial}
                                    </div>

                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <h2 className="text-base leading-snug font-semibold sm:text-lg">
                                                <Link
                                                    href={`/jobs/${jobId}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {job?.title ||
                                                        'Job Posting'}
                                                </Link>
                                            </h2>
                                            {isClosed ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-red-500/40 bg-red-500/10 text-xs text-red-700 dark:text-red-400"
                                                >
                                                    Closed
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-500/40 bg-emerald-500/10 text-xs text-emerald-700 dark:text-emerald-400"
                                                >
                                                    Published
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
                                            {job?.location && (
                                                <span className="flex items-center gap-1">
                                                    <MapPin className="h-4 w-4" />
                                                    {job.location}
                                                </span>
                                            )}
                                            {employmentType && (
                                                <span className="flex items-center gap-1">
                                                    <Briefcase className="h-4 w-4" />
                                                    {employmentType}
                                                </span>
                                            )}
                                            {salarySummary && (
                                                <span className="font-medium text-foreground">
                                                    {salarySummary}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-2 self-end sm:self-center">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={`/jobs/${jobId}`}>
                                                <ExternalLink
                                                    className="mr-1.5 h-4 w-4"
                                                    aria-hidden="true"
                                                />
                                                View
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => handleUnsave(jobId)}
                                        >
                                            <Trash2
                                                className="mr-1.5 h-4 w-4"
                                                aria-hidden="true"
                                            />
                                            Remove
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {pagination && pagination.last_page > 1 && (
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
