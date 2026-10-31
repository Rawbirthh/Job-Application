import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import SalaryBandController from '@/actions/App/Http/Controllers/Admin/SalaryBandController';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SplitLayout from '@/components/admin/split-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { PaginatedData } from '@/types/authorization';

export type SalaryBand = {
    id: number;
    label: string;
    currency: string;
    lower_bound: string;
    upper_bound: string;
    is_active: boolean;
    pay_period: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
    } | null;
};

type PayPeriodOption = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
};

type AdminSalaryBandsIndexProps = {
    salaryBands: PaginatedData<SalaryBand>;
    payPeriods: PayPeriodOption[];
    filters: {
        search: string;
        pay_period_id: number | null;
    };
    canManageSalaryBands: boolean;
};

export default function AdminSalaryBandsIndex({
    salaryBands,
    payPeriods,
    filters,
    canManageSalaryBands,
}: AdminSalaryBandsIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [payPeriodId, setPayPeriodId] = useState<string>(
        filters.pay_period_id ? String(filters.pay_period_id) : 'all',
    );
    const [isSearching, setIsSearching] = useState(false);
    const [editingSalaryBand, setEditingSalaryBand] =
        useState<SalaryBand | null>(null);

    const form = useForm<{
        pay_period_id: string;
        label: string;
        currency: string;
        lower_bound: string;
        upper_bound: string;
        is_active: boolean;
    }>({
        pay_period_id: '',
        label: '',
        currency: 'USD',
        lower_bound: '0',
        upper_bound: '50000',
        is_active: true,
    });

    const visitIndex = (data: { search?: string; pay_period_id?: string }) => {
        router.get(SalaryBandController.index.url(), data, {
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
        visitIndex({
            search: term === '' ? undefined : term,
            pay_period_id: payPeriodId === 'all' ? undefined : payPeriodId,
        });
    };

    const startEdit = (salaryBand: SalaryBand) => {
        setEditingSalaryBand(salaryBand);
        form.setData({
            pay_period_id: salaryBand.pay_period
                ? String(salaryBand.pay_period.id)
                : '',
            label: salaryBand.label,
            currency: salaryBand.currency,
            lower_bound: salaryBand.lower_bound,
            upper_bound: salaryBand.upper_bound,
            is_active: salaryBand.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingSalaryBand(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingSalaryBand) {
            form.put(SalaryBandController.update.url(editingSalaryBand.id), {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            });
        } else {
            form.post(SalaryBandController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                    form.setData('lower_bound', '0');
                    form.setData('upper_bound', '50000');
                },
            });
        }
    };

    const handleDelete = (salaryBand: SalaryBand) => {
        if (
            confirm(
                `Are you sure you want to delete salary band "${salaryBand.label}"?`,
            )
        ) {
            router.delete(SalaryBandController.destroy.url(salaryBand.id), {
                preserveScroll: true,
            });
        }
    };

    const formatAmount = (value: string) => {
        const number = Number(value);
        if (Number.isNaN(number)) return value;
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(number);
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="salary-band-pay-period">Pay period</Label>
                <Select
                    value={form.data.pay_period_id}
                    onValueChange={(value) =>
                        form.setData('pay_period_id', value)
                    }
                    disabled={form.processing}
                >
                    <SelectTrigger
                        id="salary-band-pay-period"
                        className="w-full"
                    >
                        <SelectValue placeholder="Select pay period" />
                    </SelectTrigger>
                    <SelectContent>
                        {payPeriods
                            .filter(
                                (p) =>
                                    p.is_active ||
                                    editingSalaryBand?.pay_period?.id === p.id,
                            )
                            .map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.name}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.pay_period_id} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="salary-band-label">Label</Label>
                <Input
                    id="salary-band-label"
                    value={form.data.label}
                    onChange={(e) => form.setData('label', e.target.value)}
                    placeholder="e.g. Senior Level"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.label} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="salary-band-currency">Currency</Label>
                <Input
                    id="salary-band-currency"
                    value={form.data.currency}
                    onChange={(e) => form.setData('currency', e.target.value)}
                    placeholder="e.g. USD"
                    maxLength={3}
                    className="uppercase"
                    disabled={form.processing}
                />
                <InputError message={form.errors.currency} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="salary-band-lower-bound">Lower bound</Label>
                    <Input
                        id="salary-band-lower-bound"
                        type="number"
                        step="0.01"
                        min={0}
                        value={form.data.lower_bound}
                        onChange={(e) =>
                            form.setData('lower_bound', e.target.value)
                        }
                        disabled={form.processing}
                    />
                    <InputError message={form.errors.lower_bound} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="salary-band-upper-bound">Upper bound</Label>
                    <Input
                        id="salary-band-upper-bound"
                        type="number"
                        step="0.01"
                        min={0}
                        value={form.data.upper_bound}
                        onChange={(e) =>
                            form.setData('upper_bound', e.target.value)
                        }
                        disabled={form.processing}
                    />
                    <InputError message={form.errors.upper_bound} />
                </div>
            </div>
            <p className="text-xs text-muted-foreground">
                The lower bound is included; the upper bound is excluded. Bands
                with the same currency and pay period cannot overlap.
            </p>
            <InputError message={form.errors.upper_bound} />

            <div className="flex items-center space-x-2 pt-2">
                <Checkbox
                    id="salary-band-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="salary-band-is-active"
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
                    {editingSalaryBand
                        ? 'Update Salary Band'
                        : 'Create Salary Band'}
                </Button>
                {editingSalaryBand && (
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
                    <Label htmlFor="salary-band-search">
                        Search salary bands
                    </Label>
                    <Input
                        id="salary-band-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by label or currency"
                    />
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="salary-band-pay-period-filter">
                            Pay period
                        </Label>
                        <Select
                            value={payPeriodId}
                            onValueChange={(value) => {
                                setPayPeriodId(value);
                                visitIndex({
                                    search:
                                        search.trim() === ''
                                            ? undefined
                                            : search.trim(),
                                    pay_period_id:
                                        value === 'all' ? undefined : value,
                                });
                            }}
                        >
                            <SelectTrigger
                                id="salary-band-pay-period-filter"
                                className="w-full"
                            >
                                <SelectValue placeholder="All periods" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All periods</SelectItem>
                                {payPeriods.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
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
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            setSearch('');
                            setPayPeriodId('all');
                            visitIndex({});
                        }}
                    >
                        Clear
                    </Button>
                </div>
            </form>

            <div className="overflow-hidden rounded-lg border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-3">
                                    Label
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Currency
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Pay period
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Range
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
                            {salaryBands.data.map((salaryBand) => (
                                <tr
                                    key={salaryBand.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {salaryBand.label}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {salaryBand.currency}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {salaryBand.pay_period?.name ?? '-'}
                                        {salaryBand.pay_period &&
                                            !salaryBand.pay_period
                                                .is_active && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2 border-border text-muted-foreground"
                                                >
                                                    <XCircle className="size-3" />
                                                    Inactive
                                                </Badge>
                                            )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {salaryBand.currency}{' '}
                                        {formatAmount(salaryBand.lower_bound)} –{' '}
                                        <span className="font-medium">
                                            {'<'}{' '}
                                            {formatAmount(
                                                salaryBand.upper_bound,
                                            )}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {salaryBand.is_active ? (
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
                                        {canManageSalaryBands && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(salaryBand)
                                                    }
                                                    title="Edit salary band"
                                                    aria-label={`Edit ${salaryBand.label}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(salaryBand)
                                                    }
                                                    title="Delete salary band"
                                                    aria-label={`Delete ${salaryBand.label}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {salaryBands.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No salary bands found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {salaryBands.total > 0 && (
                <Pagination
                    currentPage={salaryBands.current_page}
                    lastPage={salaryBands.last_page}
                    perPage={salaryBands.per_page}
                    total={salaryBands.total}
                    prevPageUrl={salaryBands.prev_page_url}
                    nextPageUrl={salaryBands.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Salary Filter Bands Management" />
            <SplitLayout
                title="Salary Filter Bands"
                description="Manage compensation filter bands used for job salary filtering. These bands do not restrict the actual salary values employers may set."
                formTitle={
                    canManageSalaryBands
                        ? editingSalaryBand
                            ? `Edit Salary Band: ${editingSalaryBand.label}`
                            : 'Create New Salary Band'
                        : 'View-only access'
                }
                formDescription={
                    canManageSalaryBands
                        ? 'Configure salary band details.'
                        : 'You can browse the catalogue but cannot create, edit, or delete salary bands.'
                }
                form={
                    canManageSalaryBands ? (
                        formContent
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Your role has view-only access to salary bands.
                        </p>
                    )
                }
                table={tableContent}
            />
        </>
    );
}
