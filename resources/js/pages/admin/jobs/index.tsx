import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PaginatedData } from '@/types/authorization';
import { index as jobsIndex, show as jobShow } from '@/routes/admin/jobs';

type JobItem = {
    id: number;
    title: string;
    status: string;
    created_at: string | null;
    company: { id: number; name: string } | null;
    category: { id: number; name: string } | null;
};

type AdminJobsIndexProps = {
    jobs: PaginatedData<JobItem>;
    filters: {
        status: string;
        search: string | null;
    };
};

export default function AdminJobsIndex({ jobs, filters }: AdminJobsIndexProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'pending_review');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            jobsIndex.url(),
            { search, status },
            { preserveState: true },
        );
    };

    const handleStatusChange = (val: string) => {
        setStatus(val);
        router.get(
            jobsIndex.url(),
            { search, status: val },
            { preserveState: true },
        );
    };

    return (
        <div className="space-y-6 p-6">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">
                    Job Moderation
                </h1>
                <p className="text-sm text-muted-foreground">
                    Review, approve, or reject submitted job postings.
                </p>
            </div>

            <form
                onSubmit={handleSearch}
                className="flex flex-wrap items-end gap-4"
            >
                <div className="w-full sm:w-72">
                    <Label htmlFor="search">Search title</Label>
                    <Input
                        id="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search jobs..."
                    />
                </div>
                <div className="w-full sm:w-48">
                    <Label htmlFor="status">Status</Label>
                    <Select value={status} onValueChange={handleStatusChange}>
                        <SelectTrigger id="status">
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="pending_review">
                                Pending Review
                            </SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="closed">Closed</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button type="submit">Filter</Button>
            </form>

            <div className="rounded-lg border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-4 font-medium">Title</th>
                                <th className="p-4 font-medium">Company</th>
                                <th className="p-4 font-medium">Category</th>
                                <th className="p-4 font-medium">Status</th>
                                <th className="p-4 font-medium">Created</th>
                                <th className="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {jobs.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        No jobs found matching criteria.
                                    </td>
                                </tr>
                            ) : (
                                jobs.data.map((job) => (
                                    <tr
                                        key={job.id}
                                        className="hover:bg-muted/50"
                                    >
                                        <td className="p-4 font-medium text-foreground">
                                            {job.title}
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {job.company?.name || '—'}
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {job.category?.name || '—'}
                                        </td>
                                        <td className="p-4">
                                            <Badge variant="outline">
                                                {job.status}
                                            </Badge>
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {job.created_at
                                                ? new Date(
                                                      job.created_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="p-4 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={jobShow.url({
                                                        job: job.id,
                                                    })}
                                                >
                                                    Review
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
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
                    prevPageUrl={jobs.prev_page_url}
                    nextPageUrl={jobs.next_page_url}
                />
            )}
        </div>
    );
}
