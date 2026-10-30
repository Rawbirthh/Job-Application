import { Head } from '@inertiajs/react';
import { Bookmark, FileText, Search } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import candidate from '@/routes/candidate';

export default function CandidateDashboard() {
    return (
        <>
            <Head title="Candidate Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Candidate Portal
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Explore job opportunities, maintain your professional profile, and track your submitted applications.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Job Discovery</CardTitle>
                            <Search className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Search Jobs</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Public job search and filtering unlock in Phase 3.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Saved Jobs</CardTitle>
                            <Bookmark className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Bookmarking opportunities unlocks in Feature 20.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">My Applications</CardTitle>
                            <FileText className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Application tracking unlocks in Feature 21 &amp; 22.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <h2 className="text-lg font-semibold tracking-tight">Candidate Profile Notice</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Your account is set up as a job seeker. Candidate profile management and resume uploads will be available in Feature 18 &amp; 19.
                    </p>
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
