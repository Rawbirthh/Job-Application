import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import PayPeriodController from '@/actions/App/Http/Controllers/Admin/PayPeriodController';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SplitLayout from '@/components/admin/split-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import type { PaginatedData } from '@/types/authorization';

export type PayPeriod = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    bands_count: number;
};

type AdminPayPeriodsIndexProps = {
    payPeriods: PaginatedData<PayPeriod>;
    filters: {
        search: string;
    };
    canManagePayPeriods: boolean;
};

export default function AdminPayPeriodsIndex({
    payPeriods,
    filters,
    canManagePayPeriods,
}: AdminPayPeriodsIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingPayPeriod, setEditingPayPeriod] = useState<PayPeriod | null>(
        null,
    );

    const form = useForm<{
        name: string;
        slug: string;
        description: string;
        sort_order: number;
        is_active: boolean;
    }>({
        name: '',
        slug: '',
        description: '',
        sort_order: 0,
        is_active: true,
    });

    const visitIndex = (data: { search?: string }) => {
        router.get(PayPeriodController.index.url(), data, {
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
        visitIndex(term === '' ? {} : { search: term });
    };

    const startEdit = (payPeriod: PayPeriod) => {
        setEditingPayPeriod(payPeriod);
        form.setData({
            name: payPeriod.name,
            slug: payPeriod.slug,
            description: payPeriod.description || '',
            sort_order: payPeriod.sort_order,
            is_active: payPeriod.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingPayPeriod(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingPayPeriod) {
            form.put(PayPeriodController.update.url(editingPayPeriod.id), {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            });
        } else {
            form.post(PayPeriodController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                    form.setData('sort_order', 0);
                },
            });
        }
    };

    const handleDelete = (payPeriod: PayPeriod) => {
        if (
            confirm(
                `Are you sure you want to delete pay period "${payPeriod.name}"?`,
            )
        ) {
            router.delete(PayPeriodController.destroy.url(payPeriod.id), {
                preserveScroll: true,
            });
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="pay-period-name">Name</Label>
                <Input
                    id="pay-period-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Per month"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="pay-period-slug">Slug</Label>
                <Input
                    id="pay-period-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. month"
                    maxLength={100}
                    disabled={form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    Unique machine identifier (letters, numbers, dashes,
                    underscores).
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="pay-period-description">Description</Label>
                <Textarea
                    id="pay-period-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this pay period"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="pay-period-sort-order">Display order</Label>
                <Input
                    id="pay-period-sort-order"
                    type="number"
                    min={0}
                    max={9999}
                    value={form.data.sort_order}
                    onChange={(e) => {
                        const value = e.target.value;
                        form.setData(
                            'sort_order',
                            value === '' ? 0 : Number(value),
                        );
                    }}
                    disabled={form.processing}
                />
                <InputError message={form.errors.sort_order} />
            </div>

            <div className="flex items-center space-x-2 pt-2">
                <Checkbox
                    id="pay-period-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="pay-period-is-active"
                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                >
                    Active (available for new job postings)
                </Label>
            </div>
            <InputError message={form.errors.is_active} />

            <div className="flex items-center gap-2 pt-2">
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="flex-1"
                >
                    {form.processing && <Spinner className="mr-2" />}
                    {editingPayPeriod
                        ? 'Update Pay Period'
                        : 'Create Pay Period'}
                </Button>
                {editingPayPeriod && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={cancelEdit}
                        disabled={form.processing}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );

    const tableContent = (
        <>
            <form
                onSubmit={handleSearch}
                className="flex flex-col gap-2 sm:flex-row sm:items-end"
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="pay-period-search">
                        Search pay periods
                    </Label>
                    <Input
                        id="pay-period-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or slug"
                        maxLength={100}
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="submit"
                        disabled={isSearching}
                        variant="secondary"
                    >
                        {isSearching ? (
                            <Spinner />
                        ) : (
                            <Search className="size-4" />
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
                        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Period
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Slug
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Order
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Bands
                                </th>
                                <th scope="col" className="px-4 py-3">
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
                            {payPeriods.data.map((payPeriod) => (
                                <tr
                                    key={payPeriod.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {payPeriod.name}
                                        </div>
                                        {payPeriod.description && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {payPeriod.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {payPeriod.slug}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {payPeriod.sort_order}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {payPeriod.bands_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        {payPeriod.is_active ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400"
                                            >
                                                <CheckCircle2 className="size-3" />
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 border-border text-muted-foreground"
                                            >
                                                <XCircle className="size-3" />
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManagePayPeriods && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(payPeriod)
                                                    }
                                                    title="Edit pay period"
                                                    aria-label={`Edit ${payPeriod.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(payPeriod)
                                                    }
                                                    disabled={
                                                        payPeriod.bands_count >
                                                        0
                                                    }
                                                    title={
                                                        payPeriod.bands_count >
                                                        0
                                                            ? 'Cannot delete: referenced by salary bands'
                                                            : 'Delete pay period'
                                                    }
                                                    aria-label={`Delete ${payPeriod.name}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {payPeriods.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No pay periods found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {payPeriods.total > 0 && (
                <Pagination
                    currentPage={payPeriods.current_page}
                    lastPage={payPeriods.last_page}
                    perPage={payPeriods.per_page}
                    total={payPeriods.total}
                    prevPageUrl={payPeriods.prev_page_url}
                    nextPageUrl={payPeriods.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Pay Periods Management" />
            <SplitLayout
                title="Pay Periods"
                description="Manage compensation pay periods used for salary filters and job postings."
                formTitle={
                    canManagePayPeriods
                        ? editingPayPeriod
                            ? `Edit Pay Period: ${editingPayPeriod.name}`
                            : 'Create New Pay Period'
                        : 'View-only access'
                }
                formDescription={
                    canManagePayPeriods
                        ? 'Configure pay period details.'
                        : 'You can browse the catalogue but cannot create, edit, or delete pay periods.'
                }
                form={
                    canManagePayPeriods ? (
                        formContent
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Your role has view-only access to pay periods.
                        </p>
                    )
                }
                table={tableContent}
            />
        </>
    );
}
