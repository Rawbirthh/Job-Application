import { Head, Link, router } from '@inertiajs/react';
import { Briefcase, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create as jobsCreate,
    edit as jobsEdit,
    index as jobsIndex,
} from '@/routes/employer/jobs';
import type { JobIndexProps } from '@/types';

export default function EmployerJobsIndex({
    jobs,
    companies,
    filters,
}: JobIndexProps) {
    const [search, setSearch] = useState(filters.search || '');

    const handleCompanyChange = (value: string) => {
        router.get(
            jobsIndex.url(),
            {
                ...filters,
                company_id:
                    value && value !== 'all' ? parseInt(value, 10) : null,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusChange = (value: string) => {
        router.get(
            jobsIndex.url(),
            {
                ...filters,
                status: value && value !== 'all' ? value : null,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            jobsIndex.url(),
            {
                ...filters,
                search: search !== '' ? search : null,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Job Drafts & Postings" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Job Drafts & Postings
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Create and manage job postings and drafts for your
                            companies.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={jobsCreate()}>
                            <Plus className="size-4" />
                            Create Job Draft
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <form
                    onSubmit={handleSearch}
                    className="flex flex-wrap items-center gap-2"
                >
                    <div className="w-full sm:w-60">
                        <Input
                            placeholder="Search by job title..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <div className="w-full sm:w-48">
                        <Select
                            value={filters.company_id?.toString() ?? 'all'}
                            onValueChange={handleCompanyChange}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All companies" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All companies
                                </SelectItem>
                                {companies.map((company) => (
                                    <SelectItem
                                        key={company.id}
                                        value={company.id.toString()}
                                    >
                                        {company.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="w-full sm:w-40">
                        <Select
                            value={filters.status ?? 'all'}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="pending_review">
                                    Pending Review
                                </SelectItem>
                                <SelectItem value="published">
                                    Published
                                </SelectItem>
                                <SelectItem value="rejected">
                                    Rejected
                                </SelectItem>
                                <SelectItem value="closed">Closed</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="secondary"
                            className="w-full sm:w-auto"
                        >
                            <Search className="mr-1.5 size-4" />
                            Search
                        </Button>
                    </div>
                </form>

                {jobs.data.length === 0 ? (
                    <Card className="border-border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                            <Briefcase className="size-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                No job postings found. Create your first job
                                draft to get started.
                            </p>
                            <Button asChild>
                                <Link href={jobsCreate()}>
                                    <Plus className="size-4" />
                                    Create Job Draft
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Your jobs ({jobs.total})
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
                                                    Title & Company
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Category / Type
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Status
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3"
                                                >
                                                    Updated
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
                                            {jobs.data.map((job) => (
                                                <tr
                                                    key={job.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-foreground">
                                                            {job.title}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {job.company?.name}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="text-foreground">
                                                            {job.category?.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                job
                                                                    .employment_type
                                                                    ?.name
                                                            }
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                job.status ===
                                                                'draft'
                                                                    ? 'secondary'
                                                                    : 'default'
                                                            }
                                                        >
                                                            {job.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {new Date(
                                                            job.updated_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {job.status ===
                                                        'pending_review' ? (
                                                            <span className="text-xs text-muted-foreground italic">
                                                                Under review
                                                            </span>
                                                        ) : (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={jobsEdit.url(
                                                                        {
                                                                            job: job.id,
                                                                        },
                                                                    )}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                    Edit
                                                                </Link>
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {jobs.total > 0 && (
                                <Pagination
                                    currentPage={jobs.current_page}
                                    lastPage={jobs.last_page}
                                    perPage={jobs.per_page}
                                    total={jobs.total}
                                    prevPageUrl={jobs.links[0]?.url ?? null}
                                    nextPageUrl={
                                        jobs.links[jobs.links.length - 1]
                                            ?.url ?? null
                                    }
                                />
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
