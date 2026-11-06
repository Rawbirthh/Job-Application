import { Head, Link } from '@inertiajs/react';
import { Bookmark, Building, Briefcase, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import Pagination from '@/components/pagination';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { Application } from '@/types/candidate';

interface Props {
    applications: { data: Application[] };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}

export default function CandidateApplicationsIndex({
    applications,
    pagination,
}: Props) {
    useFlashToast();

    const statusVariants: Record<
        string,
        'default' | 'secondary' | 'outline' | 'destructive'
    > = {
        submitted: 'default',
        reviewing: 'outline',
        shortlisted: 'outline',
        interview_scheduled: 'outline',
        offer_sent: 'outline',
        hired: 'default',
        rejected: 'destructive',
        withdrawn: 'default',
    };

    const formatStatus = (status: string) => {
        const statusMap: Record<string, string> = {
            submitted: 'Submitted',
            reviewing: 'Reviewing',
            shortlisted: 'Shortlisted',
            interview_scheduled: 'Interview Scheduled',
            offer_sent: 'Offer Sent',
            hired: 'Hired',
            rejected: 'Rejected',
            withdrawn: 'Withdrawn',
        };
        return statusMap[status] || status;
    };

    const items = applications.data;

    return (
        <div className="container mx-auto max-w-6xl px-4 py-8">
            <Head title="My Applications" />

            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        My Applications
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        Track your job applications and their current status.
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
                            <Bookmark className="h-8 w-8 text-primary" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            No applications yet
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            You haven't applied to any jobs yet. Browse jobs and
                            apply to start tracking your applications.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href="/jobs">Browse Job Listings</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    {items.map((application: any) => (
                        <Card
                            key={application.id}
                            className="transition-shadow hover:shadow-md"
                        >
                            <CardContent className="flex flex-col gap-4 p-6">
                                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <CardTitle className="text-lg">
                                        <Link
                                            href={`/jobs/${application.job_id}`}
                                            className="text-primary hover:underline"
                                        >
                                            {application.job_title}
                                        </Link>
                                    </CardTitle>
                                    <Badge
                                        variant={
                                            statusVariants[
                                                application.status
                                            ] || 'default'
                                        }
                                    >
                                        {formatStatus(application.status)}
                                    </Badge>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                                    {application.company && (
                                        <span className="flex items-center gap-1">
                                            <Building className="h-4 w-4" />
                                            {application.company.name}
                                        </span>
                                    )}
                                    {application.location && (
                                        <span className="flex items-center gap-1">
                                            <MapPin className="h-4 w-4" />
                                            {application.location}
                                        </span>
                                    )}
                                    {application.employment_type && (
                                        <span className="flex items-center gap-1">
                                            <Briefcase className="h-4 w-4" />
                                            {application.employment_type}
                                        </span>
                                    )}
                                    {application.salary_min && (
                                        <span>
                                            Salary: {application.salary_min} -{' '}
                                            {application.salary_max}
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-center justify-between pt-2">
                                    <span className="text-sm text-muted-foreground">
                                        Applied on{' '}
                                        {application.applied_at
                                            ? new Date(
                                                  application.applied_at,
                                              ).toLocaleDateString('en-US', {
                                                  year: 'numeric',
                                                  month: 'long',
                                                  day: 'numeric',
                                              })
                                            : 'N/A'}
                                    </span>
                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={`/candidate/applications/${application.id}`}
                                        >
                                            View Details
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-8">
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
