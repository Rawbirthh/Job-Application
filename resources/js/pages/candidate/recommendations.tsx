import { Head, Link } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    CheckCircle2,
    MapPin,
    Sparkles,
    UserRound,
} from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFlashToast } from '@/hooks/use-flash-toast';
import candidate from '@/routes/candidate';
import type {
    RecommendationIndexProps,
    RecommendedJob,
    RecommendationReason,
} from '@/types';

type Props = RecommendationIndexProps;

function formatSalary(job: RecommendedJob): string | null {
    if (job.salary_min === null && job.salary_max === null) {
        return job.salary_negotiable ? 'Salary negotiable' : null;
    }

    const currency = job.salary_currency || 'USD';

    if (job.salary_min !== null && job.salary_max !== null) {
        return `${currency} ${job.salary_min.toLocaleString()} - ${job.salary_max.toLocaleString()}`;
    }

    if (job.salary_min !== null) {
        return `From ${currency} ${job.salary_min.toLocaleString()}`;
    }

    if (job.salary_max !== null) {
        return `Up to ${currency} ${job.salary_max.toLocaleString()}`;
    }

    return null;
}

function ReasonList({ reasons }: { reasons: RecommendationReason[] }) {
    if (reasons.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2">
            {reasons.map((reason) => (
                <div key={reason.code} className="space-y-1">
                    <Badge
                        variant="secondary"
                        title={reason.values.join(', ')}
                        className="font-normal"
                    >
                        {reason.detail}
                    </Badge>
                    {reason.values.length > 0 && (
                        <div className="flex flex-wrap gap-1 pl-1">
                            {reason.values.map((value, index) => (
                                <Badge
                                    key={`${reason.code}-${value}-${index}`}
                                    variant="outline"
                                    className="text-xs font-normal"
                                >
                                    {value}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

export default function JobRecommendations({
    recommendations,
    mode,
    profileSummary,
}: Props) {
    useFlashToast();

    const items = recommendations?.data ?? [];
    const isFallback = mode === 'fallback';

    return (
        <div className="container mx-auto max-w-6xl px-4 py-8">
            <Head title="Recommended Jobs" />

            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        {isFallback ? 'Recent jobs' : 'Recommended for you'}
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        {isFallback
                            ? 'Showing recent published jobs while we work out what suits you best.'
                            : 'Published jobs ranked against your skills, preferences and experience.'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={candidate.profile.edit()}>
                            <UserRound className="h-4 w-4" />
                            Edit profile
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href="/jobs">Browse Jobs</Link>
                    </Button>
                </div>
            </div>

            {isFallback && (
                <Card className="mb-6 border-border">
                    <CardContent className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Complete your profile to get personalised
                            recommendations.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {profileSummary.signals_available} of 5 signals
                            available
                        </p>
                    </CardContent>
                </Card>
            )}

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Sparkles className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            There are no published jobs right now
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Check back later, or browse the full job list to see
                            everything that is published.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href="/jobs">Browse Job Listings</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    {items.map((job) => {
                        const salary = formatSalary(job);

                        return (
                            <Card
                                key={job.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-4 p-6">
                                    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                        <div className="flex-1 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="text-xl font-semibold">
                                                    <Link
                                                        href={`/jobs/${job.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {job.title}
                                                    </Link>
                                                </h2>
                                                {job.has_applied && (
                                                    <Badge variant="outline">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        Applied
                                                    </Badge>
                                                )}
                                                {job.is_saved && (
                                                    <Badge variant="outline">
                                                        Saved
                                                    </Badge>
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                                {job.company && (
                                                    <span className="flex items-center gap-1 font-medium text-foreground">
                                                        <Building2 className="h-4 w-4" />
                                                        {job.company.name}
                                                    </span>
                                                )}
                                                {job.location && (
                                                    <span className="flex items-center gap-1">
                                                        <MapPin className="h-4 w-4" />
                                                        {job.location}
                                                    </span>
                                                )}
                                                {job.employment_type && (
                                                    <span className="flex items-center gap-1">
                                                        <Briefcase className="h-4 w-4" />
                                                        {
                                                            job.employment_type
                                                                .name
                                                        }
                                                    </span>
                                                )}
                                                {job.workplace_arrangement && (
                                                    <span className="flex items-center gap-1">
                                                        {
                                                            job
                                                                .workplace_arrangement
                                                                .name
                                                        }
                                                    </span>
                                                )}
                                                {salary && (
                                                    <span>{salary}</span>
                                                )}
                                            </div>
                                        </div>

                                        {job.match_score !== null && (
                                            <Badge
                                                variant="default"
                                                className="shrink-0"
                                            >
                                                Match score {job.match_score}
                                            </Badge>
                                        )}
                                    </div>

                                    <ReasonList reasons={job.reasons} />

                                    <div className="flex justify-end">
                                        <Button asChild variant="outline">
                                            <Link href={`/jobs/${job.id}`}>
                                                View job
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {recommendations && recommendations.last_page > 1 && (
                <div className="mt-8">
                    <Pagination
                        currentPage={recommendations.current_page}
                        lastPage={recommendations.last_page}
                        perPage={recommendations.per_page}
                        total={recommendations.total}
                        prevPageUrl={recommendations.prev_page_url}
                        nextPageUrl={recommendations.next_page_url}
                    />
                </div>
            )}
        </div>
    );
}
