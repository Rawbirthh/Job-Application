import { Head, router, usePage } from '@inertiajs/react';
import { Search, Users, XCircle, CheckCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SplitLayout from '@/components/admin/split-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectTrigger,
    SelectValue,
    SelectItem,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import {
    index as employersIndex,
    suspend as suspendEmployer,
    reactivate as reactivateEmployer,
} from '@/routes/admin/employers';
import type { AdminEmployerIndexProps } from '@/types';
import { useFlashToast } from '@/hooks/use-flash-toast';

// Employer status options
const EMPLOYER_STATUSES = ['pending', 'approved', 'suspended'] as const;

type EmployerStatus = (typeof EMPLOYER_STATUSES)[number];

/**
 * Resolve badge colors with an explicit background/text pair so every
 * status stays legible in both light and dark themes.
 */
function statusBadgeClassName(badge: string): string {
    switch (badge) {
        case 'approved':
            return 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300';
        case 'suspended':
        case 'rejected':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300';
        default:
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300';
    }
}

export default function AdminEmployersIndex({
    employers,
    filters,
    canManageEmployers,
}: AdminEmployerIndexProps) {
    const { errors } = usePage<{
        errors: { search?: string; status?: string };
    }>().props;
    const [search, setSearch] = useState(filters.search);
    const [selectedStatus, setSelectedStatus] = useState<
        'pending' | 'approved' | 'suspended'
    >('pending');
    const [suspensionReason, setSuspensionReason] = useState<string | null>(
        null,
    );
    const [isSearching, setIsSearching] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const [isFormSubmitting, setIsFormSubmitting] = useState(false);
    const [selectedEmployerId, setSelectedEmployerId] = useState<number | null>(
        null,
    );

    useFlashToast();

    const cancelForm = () => {
        setSuspensionReason(null);
        form.reset();
        form.clearErrors();
        setSelectedEmployerId(null);
    };

    const form = useForm<{
        status: EmployerStatus;
        suspension_reason: string | null;
    }>({
        status: selectedStatus,
        suspension_reason: null,
    });

    const visitIndex = (data: {
        search?: string;
        status?: EmployerStatus | undefined;
    }) => {
        router.get(employersIndex.url(), data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setIsSearching(true);
                setIsProcessing(false);
            },
            onFinish: () => {
                setIsSearching(false);
            },
        });
    };

    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const term = search.trim();
        setSearch(term);

        visitIndex(term === '' ? {} : { search: term });
    };

    const handleStatusChange = (status: EmployerStatus) => {
        setSelectedStatus(status);

        form.setData('status', status);
        form.clearErrors('status');

        if (status === 'suspended') {
            setSuspensionReason('');
        } else {
            setSuspensionReason(null);
            form.setData('suspension_reason', null);
        }
    };

    const handleSuspensionReasonChange = (
        event: React.ChangeEvent<HTMLTextAreaElement>,
    ) => {
        const value = event.target.value;
        setSuspensionReason(value);
        form.setData('suspension_reason', value);
    };

    const handleFormSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing || isFormSubmitting) {
            return;
        }

        // When status is suspended, suspension_reason is required
        if (form.data.status === 'suspended' && !form.data.suspension_reason) {
            form.setError(
                'suspension_reason',
                'Suspension reason is required when suspending an employer account',
            );
            return;
        }

        if (form.data.status === 'pending') {
            form.setError(
                'status',
                'Choose Suspend or Reactivate to update this employer.',
            );
            return;
        }

        let employerId = selectedEmployerId;

        if (!employerId) {
            if (employers.data.length === 0) {
                setIsFormSubmitting(false);
                return;
            }

            employerId = employers.data[0].id;
            setSelectedEmployerId(employerId);
        }

        setIsFormSubmitting(true);

        const submissionData = {
            status: form.data.status,
            suspension_reason: form.data.suspension_reason,
        };

        const options = {
            preserveScroll: true,
            onFinish: () => setIsFormSubmitting(false),
        };

        // Determine which route to post to based on status
        if (form.data.status === 'suspended') {
            router.post(
                suspendEmployer.url({ user: employerId }),
                submissionData,
                options,
            );
        } else {
            router.post(
                reactivateEmployer.url({ user: employerId }),
                submissionData,
                options,
            );
        }
    };

    // Table content
    const tableContent = (
        <>
            <form
                onSubmit={handleSearch}
                className="flex flex-col gap-2 sm:flex-row sm:items-end"
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="employer-search">Search employers</Label>
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

                    {filters.search && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                visitIndex({});
                            }}
                        >
                            Clear
                        </Button>
                    )}
                </div>
            </form>

            <div className="overflow-hidden rounded-lg border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Employer accounts</caption>

                        <thead className="border-b border-border bg-muted/50 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Employer
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Email
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Company count
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Suspension reason
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
                                    <td className="px-4 py-3 font-medium">
                                        {employer.name}
                                    </td>
                                    <td className="px-4 py-3 break-words text-muted-foreground">
                                        {employer.email}
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
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {employer.company_count ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {employer.status === 'suspended' ? (
                                            employer.suspension_reason ? (
                                                <span
                                                    className="text-xs text-muted-foreground"
                                                    title={
                                                        employer.suspension_reason
                                                    }
                                                >
                                                    {employer.suspension_reason.substring(
                                                        0,
                                                        50,
                                                    )}
                                                </span>
                                            ) : (
                                                <span
                                                    className="text-xs text-muted-foreground"
                                                    title="No suspension reason provided"
                                                >
                                                    No reason provided
                                                </span>
                                            )
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManageEmployers ? (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Suspend"
                                                    onClick={() => {
                                                        setSelectedStatus(
                                                            'suspended',
                                                        );
                                                        setSuspensionReason('');
                                                        form.setData(
                                                            'status',
                                                            'suspended' as EmployerStatus,
                                                        );
                                                        form.setData(
                                                            'suspension_reason',
                                                            null,
                                                        );
                                                        setSelectedEmployerId(
                                                            employer.id,
                                                        );
                                                    }}
                                                >
                                                    <XCircle className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Reactivate"
                                                    onClick={() => {
                                                        setSelectedStatus(
                                                            'approved',
                                                        );
                                                        setSuspensionReason(
                                                            null,
                                                        );
                                                        form.setData(
                                                            'status',
                                                            'approved' as EmployerStatus,
                                                        );
                                                        form.setData(
                                                            'suspension_reason',
                                                            null,
                                                        );
                                                        setSelectedEmployerId(
                                                            employer.id,
                                                        );
                                                    }}
                                                >
                                                    <CheckCircle className="size-4" />
                                                </Button>
                                            </div>
                                        ) : (
                                            <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                View only
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {employers.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12">
                                        <div className="mx-auto flex max-w-md flex-col items-center gap-2 text-center">
                                            <Users
                                                className="size-6 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <p className="font-medium">
                                                No employers found.
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                No employer accounts match your
                                                criteria.
                                            </p>
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
        </>
    );

    // Form content - admin action form
    const formContent = (
        <form onSubmit={handleFormSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="employer-status">Status</Label>
                <Select
                    value={form.data.status}
                    onValueChange={handleStatusChange}
                    disabled={isProcessing}
                >
                    <SelectTrigger>
                        <SelectValue
                            placeholder="Select status"
                            className="w-full"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {EMPLOYER_STATUSES.map((status) => (
                            <SelectItem key={status} value={status}>
                                {status.charAt(0).toUpperCase() +
                                    status.slice(1)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError
                    message={form.errors.status ?? errors.status}
                    className="mt-1"
                />
            </div>

            {/* Suspension reason field - shown only when status is suspended */}
            {selectedStatus === 'suspended' ||
            form.data.status === 'suspended' ? (
                <div className="space-y-2">
                    <Label htmlFor="suspension-reason">Suspension reason</Label>
                    <Textarea
                        id="suspension-reason"
                        value={
                            suspensionReason ??
                            form.data.suspension_reason ??
                            ''
                        }
                        onChange={handleSuspensionReasonChange}
                        placeholder="Required when suspending an employer account"
                        rows={3}
                        maxLength={500}
                        disabled={isProcessing}
                    />
                    <p
                        className="text-xs text-muted-foreground"
                        id="suspension-reason-help"
                    >
                        Required when suspending an employer account
                    </p>
                    <InputError
                        message={
                            form.errors.suspension_reason ??
                            errors.suspension_reason
                        }
                        className="mt-1"
                    />
                </div>
            ) : null}

            <div className="flex items-center gap-2 pt-2">
                <Button
                    type="submit"
                    disabled={isFormSubmitting || isProcessing}
                    className="flex-1"
                >
                    {isFormSubmitting && <Spinner className="mr-2" />}
                    {selectedStatus === 'suspended'
                        ? 'Suspend Employer'
                        : selectedStatus === 'approved'
                          ? 'Approve Employer'
                          : 'Change Status'}
                </Button>
                {canManageEmployers && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={cancelForm}
                        disabled={isFormSubmitting || isProcessing}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );

    return (
        <>
            <Head title="Employer Account Management" />
            <SplitLayout
                title="Employer Account Management"
                description="Manage employer accounts and their suspension status."
                formTitle="Admin Employer Actions"
                formDescription="Change employer status and provide suspension reasons when applicable."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}
