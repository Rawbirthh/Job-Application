import { Head, Link } from '@inertiajs/react';
import { Briefcase, Building2, Plus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import employer from '@/routes/employer';
import { index as companiesIndex } from '@/routes/employer/companies';
import { create as jobsCreate } from '@/routes/employer/jobs';

export default function EmployerDashboard() {
    return (
        <>
            <Head title="Employer Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Employer Portal
                        </h1>
                        <Button asChild>
                            <Link href={jobsCreate()}>
                                <Plus className="mr-2 size-4" />
                                Create Job Draft
                            </Link>
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Manage your company profile, create job drafts, and
                        review incoming candidate applications.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Company Profile
                            </CardTitle>
                            <Building2 className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Manage</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Create and edit your company profiles.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href={companiesIndex()}>
                                        Company Profiles
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Job Drafts & Postings
                            </CardTitle>
                            <Briefcase className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Drafts</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Create and manage job drafts before submission.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button asChild size="sm">
                                    <Link href={employer.jobs.index()}>
                                        Manage Jobs
                                    </Link>
                                </Button>
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={jobsCreate()}>
                                        Create Draft
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Applications
                            </CardTitle>
                            <Users className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Review</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Review candidates who applied to your job
                                postings.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href={employer.jobs.index()}>
                                        View Job Applicants
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <h2 className="text-lg font-semibold tracking-tight">
                        Employer Guidelines
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Ensure your company profile is complete before creating
                        job drafts. Once your draft is ready, subsequent
                        features will enable submission for review and
                        publication.
                    </p>
                </div>
            </div>
        </>
    );
}

EmployerDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Employer Dashboard',
            href: employer.dashboard(),
        },
    ],
};
