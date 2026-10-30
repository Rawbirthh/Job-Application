import { Head } from '@inertiajs/react';
import { Briefcase, Building2, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import employer from '@/routes/employer';

export default function EmployerDashboard() {
    return (
        <>
            <Head title="Employer Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Employer Portal
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Manage your company profile, publish job listings, and review incoming candidate applications.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Company Profile</CardTitle>
                            <Building2 className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Pending Setup</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Company verification and profile creation unlock in Feature 11 &amp; 12.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Active Jobs</CardTitle>
                            <Briefcase className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Job creation, drafts, and publication unlock in Phase 2.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Applications</CardTitle>
                            <Users className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">0</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Candidate applications review pipeline unlocks in Phase 4.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <h2 className="text-lg font-semibold tracking-tight">Employer Onboarding Notice</h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Your account has the employer capability. Following the roadmap, administrator review and company profile creation will be activated in upcoming Phase 1 &amp; Phase 2 features.
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
