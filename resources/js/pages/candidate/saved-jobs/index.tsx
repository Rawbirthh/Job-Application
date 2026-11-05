import { Head, Link, router } from '@inertiajs/react';
import {
    Bookmark,
    Briefcase,
    Building,
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

    const handleUnsave = (jobId: number) => {
        router.delete(`/candidate/saved-jobs/${jobId}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="container mx-auto max-w-6xl px-4 py-8">
            <Head title="Saved Jobs" />

            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Saved Jobs
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        Manage your bookmarked job postings and review
                        application status.
                    </p>
                </div>
                <Button asChild variant="outline">
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
                <div className="space-y-4">
                    {items.map((saved: any) => {
                        const job = saved.job || saved;
                        const company = job?.company;
                        const jobId = job?.id || saved.job_posting_id;
                        const isClosed = job?.status === 'closed';

                        return (
                            <Card
                                key={saved.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-4 p-6 md:flex-row md:items-center md:justify-between">
                                    <div className="flex-1 space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-xl font-semibold">
                                                <Link
                                                    href={`/jobs/${jobId}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {job?.title ||
                                                        'Job Posting'}
                                                </Link>
                                            </h2>
                                            {isClosed ? (
                                                <Badge variant="destructive">
                                                    Closed
                                                </Badge>
                                            ) : (
                                                <Badge variant="default">
                                                    Published
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                            {company?.name && (
                                                <span className="flex items-center gap-1 font-medium text-foreground">
                                                    <Building className="h-4 w-4" />
                                                    {company.name}
                                                </span>
                                            )}
                                            {job?.location && (
                                                <span className="flex items-center gap-1">
                                                    <MapPin className="h-4 w-4" />
                                                    {job.location}
                                                </span>
                                            )}
                                            {job?.employment_type && (
                                                <span className="flex items-center gap-1">
                                                    <Briefcase className="h-4 w-4" />
                                                    {job.employment_type.name}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 self-end md:self-center">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={`/jobs/${jobId}`}>
                                                <ExternalLink className="mr-1.5 h-4 w-4" />
                                                View
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => handleUnsave(jobId)}
                                        >
                                            <Trash2 className="mr-1.5 h-4 w-4" />
                                            Remove
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-6 flex justify-center">
                            <Pagination links={pagination as any} />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
