import { Head, Link, usePage } from '@inertiajs/react';
import {
    Bookmark,
    Briefcase,
    Clock,
    FileText,
    Search,
    ShieldAlert,
    Sparkles,
    UserCircle,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import candidate from '@/routes/candidate';
import employer from '@/routes/employer';
import { index as employerOnboardingIndex } from '@/routes/employer/onboarding';
import type { Auth } from '@/types';

export default function CandidateDashboard() {
    const { auth, applicationCount } = usePage<{
        auth: Auth;
        applicationCount: number;
    }>().props;
    const employerStatus = auth.employer_status;

    const renderEmployerAccess = () => {
        if (employerStatus === 'approved') {
            return (
                <>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Your employer access is active.
                    </p>
                    <div className="mt-3">
                        <Button asChild size="sm">
                            <Link href={employer.dashboard()}>
                                Employer dashboard
                            </Link>
                        </Button>
                    </div>
                </>
            );
        }

        if (employerStatus === 'pending') {
            return (
                <>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Your application is under review.
                    </p>
                    <div className="mt-3">
                        <Button asChild size="sm" variant="outline">
                            <Link href={employerOnboardingIndex()}>
                                View application status
                            </Link>
                        </Button>
                    </div>
                </>
            );
        }

        if (employerStatus === 'suspended') {
            return (
                <>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Employer access is currently suspended.
                    </p>
                    <div className="mt-3">
                        <Button asChild size="sm" variant="outline">
                            <Link href={employerOnboardingIndex()}>
                                View status
                            </Link>
                        </Button>
                    </div>
                </>
            );
        }

        if (employerStatus === 'rejected') {
            return (
                <>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Your previous application was not approved. You may
                        reapply.
                    </p>
                    <div className="mt-3">
                        <Button asChild size="sm">
                            <Link href={employerOnboardingIndex()}>
                                Reapply
                            </Link>
                        </Button>
                    </div>
                </>
            );
        }

        return (
            <>
                <p className="mt-1 text-xs text-muted-foreground">
                    Request employer capability for your account.
                </p>
                <div className="mt-3">
                    <Button asChild size="sm">
                        <Link href={employerOnboardingIndex()}>
                            Become an employer
                        </Link>
                    </Button>
                </div>
            </>
        );
    };

    const employerIcon =
        employerStatus === 'approved' ? (
            <Briefcase className="size-4 text-teal-600 dark:text-teal-400" />
        ) : employerStatus === 'pending' ? (
            <Clock className="size-4 text-amber-600 dark:text-amber-400" />
        ) : employerStatus === 'suspended' ? (
            <ShieldAlert className="size-4 text-red-600 dark:text-red-400" />
        ) : (
            <Briefcase className="size-4 text-teal-600 dark:text-teal-400" />
        );

    return (
        <>
            <Head title="Candidate Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Candidate Portal
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Explore job opportunities, maintain your professional
                        profile, and track your submitted applications.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Job Discovery
                            </CardTitle>
                            <Search className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                Search Jobs
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Public job search and filtering unlock in Phase
                                3.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Saved Jobs
                            </CardTitle>
                            <Bookmark className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <p className="mt-1 text-xs text-muted-foreground">
                                View and manage your bookmarked career
                                opportunities.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href="/candidate/saved-jobs">
                                        View saved jobs
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                My Applications
                            </CardTitle>
                            <FileText className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {applicationCount ?? 0}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Application tracking unlocks in Feature 21 &amp;
                                22.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Candidate Profile
                            </CardTitle>
                            <UserCircle className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                Manage profile
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Update your headline, skills, education, and
                                work experience.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href={candidate.profile.edit()}>
                                        Edit profile
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Recommended Jobs
                            </CardTitle>
                            <Sparkles className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                Matched to your profile
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Published jobs ranked against your skills,
                                preferences and experience.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href="/candidate/recommendations">
                                        See recommendations
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Resume Management
                            </CardTitle>
                            <FileText className="size-4 text-blue-600 dark:text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                Private resume
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Upload, replace, or manage your private PDF
                                resume for job applications.
                            </p>
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <Link href={candidate.resumes.index()}>
                                        Manage resume
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Employer access
                            </CardTitle>
                            {employerIcon}
                        </CardHeader>
                        <CardContent>{renderEmployerAccess()}</CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CandidateDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Dashboard',
            href: candidate.dashboard(),
        },
    ],
};
