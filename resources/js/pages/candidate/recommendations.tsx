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
import { cn } from '@/lib/utils';
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

function matchScoreClasses(score: number): string {
    if (score >= 70) {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (score >= 40) {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function matchScoreLabel(score: number): string {
    if (score >= 70) {
        return 'Strong match';
    }

    if (score >= 40) {
        return 'Good match';
    }

    return 'Low match';
}

function MatchScoreBadge({ score }: { score: number }) {
    return (
        <Badge
            variant="outline"
            className={cn('shrink-0 gap-1.5', matchScoreClasses(score))}
            title={`${score}/100 against your profile signals`}
        >
            <span
                aria-hidden="true"
                className="inline-block size-1.5 rounded-full bg-current opacity-70"
            />
            {matchScoreLabel(score)} · {score}
        </Badge>
    );
}

const MAX_REASONS_SHOWN = 3;
const MAX_VALUES_SHOWN = 3;

function ReasonList({ reasons }: { reasons: RecommendationReason[] }) {
    if (reasons.length === 0) {
        return null;
    }

    const visible = reasons.slice(0, MAX_REASONS_SHOWN);
    const extra = reasons.length - visible.length;

    return (
        <div className="flex flex-wrap items-center gap-1.5 border-t border-border pt-3">
            <span className="mr-1 text-xs font-medium text-muted-foreground">
                Why matched
            </span>
            {visible.map((reason) => (
                <Badge
                    key={reason.code}
                    variant="secondary"
                    className="max-w-full gap-1 font-normal"
                    title={
                        reason.values.length > 0
                            ? reason.values.join(', ')
                            : reason.detail
                    }
                >
                    <span className="truncate">{reason.detail}</span>
                    {reason.values.length > 0 && (
                        <span className="truncate text-muted-foreground">
                            ·{' '}
                            {reason.values
                                .slice(0, MAX_VALUES_SHOWN)
                                .join(', ')}
                            {reason.values.length > MAX_VALUES_SHOWN &&
                                ` +${reason.values.length - MAX_VALUES_SHOWN}`}
                        </span>
                    )}
                </Badge>
            ))}
            {extra > 0 && (
                <span
                    className="text-xs text-muted-foreground"
                    title={reasons
                        .slice(MAX_REASONS_SHOWN)
                        .map((reason) => reason.detail)
                        .join(' · ')}
                >
                    +{extra} more
                </span>
            )}
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
        <div className="flex w-full flex-1 flex-col gap-6 p-6 md:p-8">
            <Head title="Recommended Jobs" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        {isFallback ? 'Recent jobs' : 'Recommended for you'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground md:text-base">
                        {isFallback
                            ? 'Showing recent published jobs while we work out what suits you best.'
                            : `Published jobs ranked against your profile · ${recommendations.total} ${recommendations.total === 1 ? 'result' : 'results'}`}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href={candidate.profile.edit()}>
                            <UserRound className="h-4 w-4" />
                            Edit profile
                        </Link>
                    </Button>
                    <Button asChild size="sm">
                        <Link href="/jobs">Browse Jobs</Link>
                    </Button>
                </div>
            </div>

            {isFallback && (
                <div className="flex flex-col gap-3 rounded-lg border border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center sm:gap-4">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Sparkles className="size-4" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium">
                            Complete your profile to unlock personalized
                            recommendations
                        </p>
                        <div className="mt-1.5 flex items-center gap-2">
                            <div
                                className="h-1.5 w-40 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuemin={0}
                                aria-valuemax={5}
                                aria-valuenow={profileSummary.signals_available}
                                aria-label="Profile signals completed"
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-all"
                                    style={{
                                        width: `${Math.min(100, (profileSummary.signals_available / 5) * 100)}%`,
                                    }}
                                />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {profileSummary.signals_available} of 5 signals
                            </span>
                        </div>
                    </div>
                    <Button asChild size="sm" className="shrink-0">
                        <Link href={candidate.profile.edit()}>
                            Finish profile
                        </Link>
                    </Button>
                </div>
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
                <div className="space-y-3">
                    {items.map((job) => {
                        const salary = formatSalary(job);

                        return (
                            <Card
                                key={job.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-3 p-4">
                                    <div className="flex items-start gap-4">
                                        {job.company?.logo_url ? (
                                            <img
                                                src={job.company.logo_url}
                                                alt={job.company.name}
                                                className="hidden size-12 shrink-0 rounded-lg border border-border object-cover sm:block"
                                            />
                                        ) : (
                                            <div
                                                aria-hidden="true"
                                                className="hidden size-12 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground sm:flex"
                                            >
                                                <Building2 className="size-6" />
                                            </div>
                                        )}

                                        <div className="min-w-0 flex-1 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <h2 className="text-base leading-snug font-semibold sm:text-lg">
                                                    <Link
                                                        href={`/jobs/${job.id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {job.title}
                                                    </Link>
                                                </h2>
                                                {job.has_applied && (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 text-xs"
                                                    >
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        Applied
                                                    </Badge>
                                                )}
                                                {job.is_saved && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        Saved
                                                    </Badge>
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground sm:text-sm">
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
                                                    <span>
                                                        {
                                                            job
                                                                .workplace_arrangement
                                                                .name
                                                        }
                                                    </span>
                                                )}
                                                {salary && (
                                                    <span className="font-medium text-foreground">
                                                        {salary}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            {job.match_score !== null && (
                                                <MatchScoreBadge
                                                    score={job.match_score}
                                                />
                                            )}
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link href={`/jobs/${job.id}`}>
                                                    View job
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>

                                    <ReasonList reasons={job.reasons} />
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {recommendations && recommendations.last_page > 1 && (
                <div className="mt-2">
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
