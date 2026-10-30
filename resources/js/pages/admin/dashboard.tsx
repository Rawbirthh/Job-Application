import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ShieldCheck, UserCheck, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import admin from '@/routes/admin';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Platform Administration
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Oversee platform governance, user accounts, system
                        roles, and access controls.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                User Management
                            </CardTitle>
                            <Users className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Accounts</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Manage user accounts, verify personas, and
                                review role assignments.
                            </p>
                            <div className="mt-4">
                                <Button
                                    asChild
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={admin.users.index()}>
                                        View Users
                                        <ArrowRight className="ml-2 size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Access Control
                            </CardTitle>
                            <ShieldCheck className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Active</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Dynamic role & permission enforcement with
                                last-admin protection.
                            </p>
                            <div className="mt-4">
                                <Button
                                    asChild
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={admin.users.index()}>
                                        Manage Roles
                                        <ArrowRight className="ml-2 size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Platform Trust
                            </CardTitle>
                            <UserCheck className="size-4 text-teal-600 dark:text-teal-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Secured</div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Server-side authorization authority protects
                                critical system resources.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <h2 className="text-lg font-semibold tracking-tight">
                        System Status & Quick Guidance
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Feature 3 custom authorization is active. As platform
                        administrator, you have access to user management, role
                        assignments, and platform oversight tools.
                    </p>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Admin Dashboard',
            href: admin.dashboard(),
        },
    ],
};
