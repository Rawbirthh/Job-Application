import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Calendar,
    Clock,
    ExternalLink,
    MapPin,
    Users,
    Bookmark,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import ReportDialog from '@/components/report/report-dialog';
import type { Auth } from '@/types';

interface JobDetail {
    id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    location: string | null;
    salary_min: string | null;
    salary_max: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    application_deadline: string | null;
    published_at: string | null;
    is_saved?: boolean;
    has_applied?: boolean;
    company: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
        overview: string | null;
        website: string | null;
        location: string | null;
        industry: string | null;
        size_band: string | null;
    } | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    pay_period: { id: number; name: string } | null;
}

interface Props {
    job: JobDetail;
}

export default function PublicJobShow({ job }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;

    const formatSalary = () => {
        if (!job.salary_min && !job.salary_max) {
            return job.salary_negotiable ? 'Salary negotiable' : null;
        }
        const curr = job.salary_currency || 'USD';
        const period = job.pay_period ? ` / ${job.pay_period.name}` : '';
        if (job.salary_min && job.salary_max) {
            return `${curr} ${Number(job.salary_min).toLocaleString()} - ${Number(job.salary_max).toLocaleString()}${period}`;
        }
        if (job.salary_min) {
            return `From ${curr} ${Number(job.salary_min).toLocaleString()}${period}`;
        }
        if (job.salary_max) {
            return `Up to ${curr} ${Number(job.salary_max).toLocaleString()}${period}`;
        }
        return null;
    };

    const isDeadlinePassed =
        job.application_deadline &&
        new Date(job.application_deadline) < new Date();

    const isJobSeeker = auth.user && auth.roles.includes('job_seeker');
    const isEmployerOrAdmin =
        auth.user &&
        (auth.roles.includes('employer') || auth.roles.includes('admin'));

    const salaryText = formatSalary();

    return (
        <>
            <Head title={`${job.title} at ${job.company?.name || 'Company'}`}>
                <meta
                    name="description"
                    content={
                        job.description.substring(0, 155) +
                        (job.description.length > 155 ? '...' : '')
                    }
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    {/* Back navigation */}
                    <div className="mb-6 flex items-center justify-between">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/jobs">
                                <ArrowLeft className="size-4" />
                                Back to job listings
                            </Link>
                        </Button>

                        <div className="flex items-center gap-2">
                            {isJobSeeker && (
                                <Button
                                    variant={
                                        job.is_saved ? 'secondary' : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => {
                                        if (job.is_saved) {
                                            router.delete(
                                                `/candidate/saved-jobs/${job.id}`,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        // Assuming a state-based update would be handled by props
                                                        // or full page reload by inertia on success for simplicity
                                                        router.reload();
                                                    },
                                                },
                                            );
                                        } else {
                                            router.post(
                                                '/candidate/saved-jobs',
                                                { job_posting_id: job.id },
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        router.reload();
                                                    },
                                                },
                                            );
                                        }
                                    }}
                                >
                                    <Bookmark
                                        className={`mr-2 size-4 ${job.is_saved ? 'fill-current' : ''}`}
                                    />
                                    {job.is_saved ? 'Saved' : 'Save Job'}
                                </Button>
                            )}

                            <ReportDialog job={job} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                        {/* Main content */}
                        <div className="lg:col-span-2">
                            {/* Job header */}
                            <div className="mb-8">
                                <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                    {job.title}
                                </h1>

                                {/* Company info */}
                                {job.company && (
                                    <div className="mt-4 flex items-center gap-3">
                                        {job.company.logo_url ? (
                                            <img
                                                src={job.company.logo_url}
                                                alt={job.company.name}
                                                className="size-12 rounded-lg border border-border object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-12 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground">
                                                <Building2 className="size-6" />
                                            </div>
                                        )}
                                        <div>
                                            <h2 className="text-lg font-semibold text-foreground">
                                                {job.company.name}
                                            </h2>
                                            {job.company.location && (
                                                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                                    <MapPin className="size-3.5" />
                                                    {job.company.location}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Job meta badges */}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {job.category && (
                                        <Badge variant="secondary">
                                            {job.category.name}
                                        </Badge>
                                    )}
                                    {job.employment_type && (
                                        <Badge variant="outline">
                                            {job.employment_type.name}
                                        </Badge>
                                    )}
                                    {job.workplace_arrangement && (
                                        <Badge variant="outline">
                                            {job.workplace_arrangement.name}
                                        </Badge>
                                    )}
                                    {job.experience_level && (
                                        <Badge variant="outline">
                                            {job.experience_level.name}
                                        </Badge>
                                    )}
                                    {job.location && (
                                        <Badge variant="outline">
                                            <MapPin className="size-3" />
                                            {job.location}
                                        </Badge>
                                    )}
                                </div>

                                {/* Job meta info */}
                                <div className="mt-4 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    {job.published_at && (
                                        <span className="flex items-center gap-1.5">
                                            <Calendar className="size-4" />
                                            Posted{' '}
                                            {new Date(
                                                job.published_at,
                                            ).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </span>
                                    )}
                                    {job.application_deadline && (
                                        <span
                                            className={`flex items-center gap-1.5 ${
                                                isDeadlinePassed
                                                    ? 'text-destructive'
                                                    : ''
                                            }`}
                                        >
                                            <Clock className="size-4" />
                                            Deadline:{' '}
                                            {new Date(
                                                job.application_deadline,
                                            ).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </span>
                                    )}
                                </div>
                            </div>

                            <Separator className="mb-8" />

                            {/* Job description */}
                            <section className="mb-8">
                                <h2 className="mb-4 text-xl font-semibold text-foreground">
                                    Job Description
                                </h2>
                                <div className="text-base leading-relaxed whitespace-pre-wrap text-foreground">
                                    {job.description}
                                </div>
                            </section>

                            {/* Responsibilities */}
                            {job.responsibilities && (
                                <>
                                    <Separator className="mb-8" />
                                    <section className="mb-8">
                                        <h2 className="mb-4 text-xl font-semibold text-foreground">
                                            Responsibilities
                                        </h2>
                                        <div className="text-base leading-relaxed whitespace-pre-wrap text-foreground">
                                            {job.responsibilities}
                                        </div>
                                    </section>
                                </>
                            )}

                            {/* Requirements */}
                            {job.requirements && (
                                <>
                                    <Separator className="mb-8" />
                                    <section className="mb-8">
                                        <h2 className="mb-4 text-xl font-semibold text-foreground">
                                            Requirements
                                        </h2>
                                        <div className="text-base leading-relaxed whitespace-pre-wrap text-foreground">
                                            {job.requirements}
                                        </div>
                                    </section>
                                </>
                            )}

                            {/* Compensation */}
                            {salaryText && (
                                <>
                                    <Separator className="mb-8" />
                                    <section className="mb-8">
                                        <h2 className="mb-4 text-xl font-semibold text-foreground">
                                            Compensation
                                        </h2>
                                        <p className="text-lg font-medium text-foreground">
                                            {salaryText}
                                        </p>
                                        {job.salary_negotiable &&
                                            (job.salary_min ||
                                                job.salary_max) && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    Salary is negotiable
                                                </p>
                                            )}
                                    </section>
                                </>
                            )}

                            {/* Call to action - mobile/bottom */}
                            <div className="mt-8 lg:hidden">
                                {!auth.user && (
                                    <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                                        <h3 className="mb-3 text-lg font-semibold text-foreground">
                                            Ready to apply?
                                        </h3>
                                        <p className="mb-4 text-sm text-muted-foreground">
                                            Sign in or create an account to
                                            apply for this role.
                                        </p>
                                        <div className="flex flex-col gap-2 sm:flex-row">
                                            <Button className="flex-1" asChild>
                                                <Link href="/login">
                                                    Sign in to apply
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="flex-1"
                                                asChild
                                            >
                                                <Link href="/register">
                                                    Register
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {isJobSeeker && (
                                    <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                                        <h3 className="mb-3 text-lg font-semibold text-foreground">
                                            Ready to apply?
                                        </h3>
                                        {isDeadlinePassed || job.has_applied ? (
                                            <Button
                                                size="lg"
                                                className="w-full"
                                                disabled
                                            >
                                                {isDeadlinePassed
                                                    ? 'Application deadline passed'
                                                    : 'Applied'}
                                            </Button>
                                        ) : (
                                            <Button
                                                size="lg"
                                                className="w-full"
                                                asChild
                                            >
                                                <Link
                                                    href={`/candidate/jobs/${job.id}/apply`}
                                                >
                                                    Apply for this role
                                                </Link>
                                            </Button>
                                        )}
                                        {isDeadlinePassed &&
                                            !job.has_applied && (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    This position is no longer
                                                    accepting applications.
                                                </p>
                                            )}
                                    </div>
                                )}

                                {isEmployerOrAdmin && (
                                    <div className="rounded-xl border border-border bg-muted/50 p-6">
                                        <p className="text-sm text-muted-foreground">
                                            You are viewing this job as{' '}
                                            {auth.roles.includes('admin')
                                                ? 'an administrator'
                                                : 'an employer'}
                                            .
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="lg:col-span-1">
                            <div className="sticky top-8 space-y-6">
                                {/* Company information card */}
                                {job.company && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <Building2 className="size-5" />
                                                About the Company
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div>
                                                <h3 className="mb-2 font-semibold text-foreground">
                                                    {job.company.name}
                                                </h3>
                                                {job.company.overview && (
                                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                                        {job.company.overview}
                                                    </p>
                                                )}
                                            </div>

                                            <Separator />

                                            <dl className="space-y-3 text-sm">
                                                {job.company.industry && (
                                                    <div>
                                                        <dt className="font-medium text-muted-foreground">
                                                            Industry
                                                        </dt>
                                                        <dd className="mt-1 text-foreground">
                                                            {
                                                                job.company
                                                                    .industry
                                                            }
                                                        </dd>
                                                    </div>
                                                )}
                                                {job.company.size_band && (
                                                    <div>
                                                        <dt className="font-medium text-muted-foreground">
                                                            Company Size
                                                        </dt>
                                                        <dd className="mt-1 flex items-center gap-1.5 text-foreground">
                                                            <Users className="size-3.5" />
                                                            {
                                                                job.company
                                                                    .size_band
                                                            }
                                                        </dd>
                                                    </div>
                                                )}
                                                {job.company.location && (
                                                    <div>
                                                        <dt className="font-medium text-muted-foreground">
                                                            Location
                                                        </dt>
                                                        <dd className="mt-1 flex items-center gap-1.5 text-foreground">
                                                            <MapPin className="size-3.5" />
                                                            {
                                                                job.company
                                                                    .location
                                                            }
                                                        </dd>
                                                    </div>
                                                )}
                                                {job.company.website && (
                                                    <div>
                                                        <dt className="font-medium text-muted-foreground">
                                                            Website
                                                        </dt>
                                                        <dd className="mt-1">
                                                            <a
                                                                href={
                                                                    job.company
                                                                        .website
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 text-primary underline-offset-4 transition-colors hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                            >
                                                                Visit website
                                                                <ExternalLink className="size-3" />
                                                                <span className="sr-only">
                                                                    (opens in
                                                                    new tab)
                                                                </span>
                                                            </a>
                                                        </dd>
                                                    </div>
                                                )}
                                            </dl>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* Call to action - desktop sidebar */}
                                <div className="hidden lg:block">
                                    {!auth.user && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>
                                                    Ready to apply?
                                                </CardTitle>
                                                <CardDescription>
                                                    Sign in or create an account
                                                    to apply for this role.
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-2">
                                                <Button asChild>
                                                    <Link href="/login">
                                                        Sign in to apply
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link href="/register">
                                                        Register
                                                    </Link>
                                                </Button>
                                            </CardContent>
                                        </Card>
                                    )}

                                    {isJobSeeker && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle>
                                                    Ready to apply?
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                {isDeadlinePassed ||
                                                job.has_applied ? (
                                                    <Button
                                                        size="lg"
                                                        className="w-full"
                                                        disabled
                                                    >
                                                        {isDeadlinePassed
                                                            ? 'Application deadline passed'
                                                            : 'Applied'}
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="lg"
                                                        className="w-full"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/candidate/jobs/${job.id}/apply`}
                                                        >
                                                            <Briefcase className="size-4" />
                                                            Apply for this role
                                                        </Link>
                                                    </Button>
                                                )}
                                                {isDeadlinePassed &&
                                                    !job.has_applied && (
                                                        <p className="mt-3 text-xs text-muted-foreground">
                                                            This position is no
                                                            longer accepting
                                                            applications.
                                                        </p>
                                                    )}
                                            </CardContent>
                                        </Card>
                                    )}

                                    {isEmployerOrAdmin && (
                                        <Card className="bg-muted/50">
                                            <CardContent className="py-4">
                                                <p className="text-sm text-muted-foreground">
                                                    You are viewing this job as{' '}
                                                    {auth.roles.includes(
                                                        'admin',
                                                    )
                                                        ? 'an administrator'
                                                        : 'an employer'}
                                                    .
                                                </p>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
