import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, Users, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index as employersIndex,
    show as employersShow,
} from '@/routes/admin/employers';
import type { AdminEmployerIndexProps, EmployerStatus } from '@/types';
import { useFlashToast } from '@/hooks/use-flash-toast';

const STATUS_OPTIONS: { value: 'all' | EmployerStatus; label: string }[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'suspended', label: 'Suspended' },
];

function statusBadgeClassName(badge: string): string {
    switch (badge) {
        case 'approved':
            return 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300';
        case 'rejected':
        case 'suspended':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300';
        case 'pending':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

export default function AdminEmployersIndex({
    employers,
    filters,
    canManageEmployers: _canManageEmployers,
}: AdminEmployerIndexProps) {
    const { errors } = usePage<{
        errors: { search?: string; status?: string };
    }>().props;

    const [search, setSearch] = useState(filters.search);
    const [selectedStatus, setSelectedStatus] = useState<
        'all' | EmployerStatus
    >(filters.status ?? 'all');
    const [isSearching, setIsSearching] = useState(false);

    useFlashToast();
    void _canManageEmployers;

    const hasFilters = filters.search !== '' || filters.status !== null;

    const visitIndex = (data: { search?: string; status?: EmployerStatus }) => {
        router.get(employersIndex.url(), data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsSearching(true),
            onFinish: () => setIsSearching(false),
        });
    };

    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const term = search.trim();
        setSearch(term);

        const payload: { search?: string; status?: EmployerStatus } = {};
        if (term !== '') {
            payload.search = term;
        }
        if (selectedStatus !== 'all') {
            payload.status = selectedStatus;
        }
        visitIndex(payload);
    };

    const handleStatusChange = (value: 'all' | EmployerStatus) => {
        setSelectedStatus(value);
        const term = search.trim();
        const payload: { search?: string; status?: EmployerStatus } = {};
        if (term !== '') {
            payload.search = term;
        }
        if (value !== 'all') {
            payload.status = value;
        }
        visitIndex(payload);
    };

    const clearFilters = () => {
        setSearch('');
        setSelectedStatus('all');
        visitIndex({});
    };

    return (
        <>
            <Head title="Employer Account Management" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Employer Account Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review employer applications and manage eligibility
                            status.
                        </p>
                    </div>

                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {employers.total}{' '}
                        {employers.total === 1 ? 'employer' : 'employers'}
                        {hasFilters && ' matching filters'}
                    </p>
                </div>

                <form
                    role="search"
                    aria-label="Search employers"
                    onSubmit={handleSearch}
                    aria-busy={isSearching}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="employer-search">
                            Search employers
                        </Label>
                        <Input
                            id="employer-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name or email"
                            maxLength={100}
                        />
                        <InputError
                            id="employer-search-error"
                            message={errors.search}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="employer-status-filter">Status</Label>
                        <Select
                            value={selectedStatus}
                            onValueChange={(value) =>
                                handleStatusChange(
                                    value as 'all' | EmployerStatus,
                                )
                            }
                        >
                            <SelectTrigger
                                id="employer-status-filter"
                                className="w-full sm:w-48"
                            >
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id="employer-status-error"
                            message={errors.status}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="submit"
                            disabled={isSearching}
                            variant="secondary"
                        >
                            {isSearching ? (
                                <Spinner />
                            ) : (
                                <Search aria-hidden="true" />
                            )}
                            Search
                        </Button>

                        {hasFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={clearFilters}
                            >
                                <X aria-hidden="true" />
                                Clear filters
                            </Button>
                        )}
                    </div>
                </form>

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Employer accounts
                            </caption>

                            <thead className="border-b border-border bg-muted/50 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Employer
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Intended company
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Status
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Submitted
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
                                {employers.data.map((employer) => (
                                    <tr
                                        key={employer.id}
                                        className="align-top transition-colors hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {employer.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {employer.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {employer.onboarding_request
                                                ?.company_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant="default"
                                                className={statusBadgeClassName(
                                                    employer.status_badge,
                                                )}
                                            >
                                                {employer.status_badge}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {employer.onboarding_request
                                                ?.submitted_at
                                                ? new Date(
                                                      employer
                                                          .onboarding_request
                                                          .submitted_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={employersShow.url({
                                                        user: employer.id,
                                                    })}
                                                >
                                                    Review
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}

                                {employers.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-12">
                                            <div className="mx-auto flex max-w-md flex-col items-center gap-2 text-center">
                                                <Users
                                                    className="size-6 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <p className="font-medium">
                                                    {hasFilters
                                                        ? 'No employers match your filters.'
                                                        : 'No applications yet.'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {hasFilters
                                                        ? 'Try adjusting your search or status filter.'
                                                        : 'Employer applications will appear here once submitted.'}
                                                </p>
                                                {hasFilters && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={clearFilters}
                                                        className="mt-2"
                                                    >
                                                        Clear filters
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {employers.total > 0 && (
                    <Pagination
                        currentPage={employers.current_page}
                        lastPage={employers.last_page}
                        perPage={employers.per_page}
                        total={employers.total}
                        prevPageUrl={employers.prev_page_url}
                        nextPageUrl={employers.next_page_url}
                    />
                )}
            </div>
        </>
    );
}
