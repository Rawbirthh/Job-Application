import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    edit as companiesEdit,
    index as companiesIndex,
} from '@/routes/employer/companies';
import type { CompanyShowProps } from '@/types';

export default function EmployerCompanyShow({ company }: CompanyShowProps) {
    return (
        <>
            <Head title={company.name} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={companiesIndex()}>
                            <ArrowLeft className="size-4" />
                            Back to companies
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex items-center gap-4">
                        {company.logo_url ? (
                            <img
                                src={company.logo_url}
                                alt={company.name}
                                className="size-16 rounded-md object-cover"
                            />
                        ) : (
                            <div className="flex size-16 items-center justify-center rounded-md bg-muted">
                                <Building2 className="size-8 text-muted-foreground" />
                            </div>
                        )}
                        <div className="flex flex-col gap-1">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                                {company.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {company.slug}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {company.is_active ? (
                            <Badge
                                variant="outline"
                                className="gap-1 border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400"
                            >
                                Active
                            </Badge>
                        ) : (
                            <Badge
                                variant="outline"
                                className="gap-1 border-border text-muted-foreground"
                            >
                                Inactive
                            </Badge>
                        )}
                        <Button asChild>
                            <Link href={companiesEdit(company.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Company information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <DetailRow
                                label="Overview"
                                value={company.overview}
                            />
                            <DetailRow
                                label="Industry"
                                value={company.industry}
                            />
                            <DetailRow
                                label="Size band"
                                value={company.size_band}
                            />
                            <DetailRow
                                label="Location"
                                value={company.location}
                            />
                            <div>
                                <span className="font-medium">Website:</span>{' '}
                                {company.website ? (
                                    <a
                                        href={company.website}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-teal-600 underline dark:text-teal-400"
                                    >
                                        {company.website}
                                    </a>
                                ) : (
                                    '—'
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">Metadata</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <span className="font-medium">Created:</span>{' '}
                                {company.created_at
                                    ? new Date(
                                          company.created_at,
                                      ).toLocaleString()
                                    : '—'}
                            </div>
                            <div>
                                <span className="font-medium">Updated:</span>{' '}
                                {company.updated_at
                                    ? new Date(
                                          company.updated_at,
                                      ).toLocaleString()
                                    : '—'}
                            </div>
                            <div>
                                <span className="font-medium">Logo path:</span>{' '}
                                {company.logo_path ?? '—'}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function DetailRow({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <span className="font-medium">{label}:</span> {value ?? '—'}
        </div>
    );
}

EmployerCompanyShow.layout = {
    breadcrumbs: [
        {
            title: 'Company Profiles',
            href: companiesIndex(),
        },
        {
            title: 'Company',
            href: companiesIndex(),
        },
    ],
};
