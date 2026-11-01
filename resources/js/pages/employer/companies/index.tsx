import { Head, Link } from '@inertiajs/react';
import { Building2, Pencil, Plus, Eye } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    index as companiesIndex,
    create as companiesCreate,
    show as companiesShow,
    edit as companiesEdit,
} from '@/routes/employer/companies';
import type { CompanyIndexProps } from '@/types';

export default function EmployerCompaniesIndex({
    companies,
    canCreate,
}: CompanyIndexProps) {
    return (
        <>
            <Head title="Company Profiles" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Company Profiles
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Manage the company profiles that own your job
                            postings.
                        </p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={companiesCreate()}>
                                <Plus className="size-4" />
                                Create company
                            </Link>
                        </Button>
                    )}
                </div>

                {companies.data.length === 0 ? (
                    <Card className="border-border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                            <Building2 className="size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                No company profiles yet. Create your first
                                company to start posting jobs.
                            </p>
                            {canCreate && (
                                <Button asChild>
                                    <Link href={companiesCreate()}>
                                        <Plus className="size-4" />
                                        Create company
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Your companies
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-hidden rounded-lg border border-border">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Company
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Slug
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right"
                                                >
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {companies.data.map((company) => (
                                                <tr
                                                    key={company.id}
                                                    className="hover:bg-muted/40"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        <div className="flex items-center gap-3">
                                                            {company.logo_url ? (
                                                                <img
                                                                    src={
                                                                        company.logo_url
                                                                    }
                                                                    alt={
                                                                        company.name
                                                                    }
                                                                    className="size-8 rounded-md object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex size-8 items-center justify-center rounded-md bg-muted">
                                                                    <Building2 className="size-4 text-muted-foreground" />
                                                                </div>
                                                            )}
                                                            <span>
                                                                {company.name}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                        {company.slug}
                                                    </td>
                                                    <td className="px-4 py-3">
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
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                title="View company"
                                                            >
                                                                <Link
                                                                    href={companiesShow(
                                                                        company.id,
                                                                    )}
                                                                >
                                                                    <Eye className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                title="Edit company"
                                                            >
                                                                <Link
                                                                    href={companiesEdit(
                                                                        company.id,
                                                                    )}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Link>
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {companies.total > 0 && (
                                <Pagination
                                    currentPage={companies.current_page}
                                    lastPage={companies.last_page}
                                    perPage={companies.per_page}
                                    total={companies.total}
                                    prevPageUrl={companies.prev_page_url}
                                    nextPageUrl={companies.next_page_url}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

EmployerCompaniesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Company Profiles',
            href: companiesIndex(),
        },
    ],
};
