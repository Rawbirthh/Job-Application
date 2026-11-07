import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Calendar,
    FileText,
    MapPin,
    User,
    Users,
} from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ApplicantItem {
    id: number;
    status: string;
    applied_at: string | null;
    candidate: {
        id: number;
        name: string;
        headline: string | null;
        location: string | null;
    };
    has_resume: boolean;
    has_cover_letter: boolean;
}

interface Props {
    job: {
        id: number;
        title: string;
        status: string;
        location: string | null;
        company: {
            id: number;
            name: string;
            slug: string;
        } | null;
    };
    applications: {
        data: ApplicantItem[];
    } & {
        // When paginator items are accessed directly or via collection
        [key: string]: unknown;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}

export default function EmployerJobApplicantsIndex({
    job,
    applications,
    pagination,
}: Props) {
    // In Inertia Laravel through() with paginate(), applications is a LengthAwarePaginator object
    // with data array inside it
    const items: ApplicantItem[] = Array.isArray(applications)
        ? applications
        : applications.data || [];

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

    const statusBadgeVariant = (
        status: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (status) {
            case 'submitted':
                return 'secondary';
            case 'reviewing':
            case 'shortlisted':
            case 'interview_scheduled':
            case 'offer_sent':
                return 'outline';
            case 'hired':
                return 'default';
            case 'rejected':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    return (
        <>
            <Head title={`Applicants - ${job.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="-ml-2 w-fit text-muted-foreground hover:text-foreground"
                    >
                        <Link href="/employer/jobs">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Job Postings
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                                Applicants
                            </h1>
                            <p className="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {job.title}
                                </span>
                                {job.company && (
                                    <>
                                        <span>•</span>
                                        <span>{job.company.name}</span>
                                    </>
                                )}
                                {job.location && (
                                    <>
                                        <span>•</span>
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="size-3" />
                                            {job.location}
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>
                        <Badge variant="outline" className="w-fit text-xs">
                            Job Status: {job.status}
                        </Badge>
                    </div>
                </div>

                {items.length === 0 ? (
                    <Card className="border-border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                                <Users className="size-7 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-lg font-semibold text-foreground">
                                    No applications yet
                                </h3>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    When candidates apply to this job posting,
                                    their profile and details will appear here.
                                </p>
                            </div>
                            <Button asChild variant="outline" className="mt-2">
                                <Link href="/employer/jobs">
                                    <Briefcase className="mr-2 size-4" />
                                    View Other Jobs
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Applications ({pagination.total})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-hidden rounded-lg border border-border">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Candidate
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Applied Date
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
                                                    Materials
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right"
                                                >
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {items.map((application) => (
                                                <tr
                                                    key={application.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">
                                                                <User className="size-4" />
                                                            </div>
                                                            <div>
                                                                <div className="font-medium text-foreground">
                                                                    {
                                                                        application
                                                                            .candidate
                                                                            .name
                                                                    }
                                                                </div>
                                                                {application
                                                                    .candidate
                                                                    .headline && (
                                                                    <div className="line-clamp-1 text-xs text-muted-foreground">
                                                                        {
                                                                            application
                                                                                .candidate
                                                                                .headline
                                                                        }
                                                                    </div>
                                                                )}
                                                                {application
                                                                    .candidate
                                                                    .location && (
                                                                    <div className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                                                        <MapPin className="size-3" />
                                                                        {
                                                                            application
                                                                                .candidate
                                                                                .location
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground">
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="size-3.5" />
                                                            {application.applied_at
                                                                ? new Date(
                                                                      application.applied_at,
                                                                  ).toLocaleDateString(
                                                                      'en-US',
                                                                      {
                                                                          year: 'numeric',
                                                                          month: 'short',
                                                                          day: 'numeric',
                                                                      },
                                                                  )
                                                                : 'N/A'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={statusBadgeVariant(
                                                                application.status,
                                                            )}
                                                            className="capitalize"
                                                        >
                                                            {formatStatus(
                                                                application.status,
                                                            )}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                                        <div className="flex items-center gap-2">
                                                            {application.has_resume && (
                                                                <span
                                                                    className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground"
                                                                    title="Resume Attached"
                                                                >
                                                                    <FileText className="size-3 text-teal-600 dark:text-teal-400" />
                                                                    Resume
                                                                </span>
                                                            )}
                                                            {application.has_cover_letter && (
                                                                <span
                                                                    className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-[11px]"
                                                                    title="Cover Letter Included"
                                                                >
                                                                    Letter
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/employer/applications/${application.id}`}
                                                            >
                                                                View Details
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {pagination.total > 0 && (
                                <Pagination
                                    currentPage={pagination.current_page}
                                    lastPage={pagination.last_page}
                                    perPage={pagination.per_page}
                                    total={pagination.total}
                                    prevPageUrl={pagination.prev_page_url}
                                    nextPageUrl={pagination.next_page_url}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
