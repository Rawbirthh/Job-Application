import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import WorkplaceArrangementController from '@/actions/App/Http/Controllers/Admin/WorkplaceArrangementController';
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

export type WorkplaceArrangement = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
};

type AdminWorkplaceArrangementsIndexProps = {
    workplaceArrangements: PaginatedData<WorkplaceArrangement>;
    filters: {
        search: string;
    };
    canManageWorkplaceArrangements: boolean;
};

export default function AdminWorkplaceArrangementsIndex({
    workplaceArrangements,
    filters,
    canManageWorkplaceArrangements,
}: AdminWorkplaceArrangementsIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingWorkplaceArrangement, setEditingWorkplaceArrangement] =
        useState<WorkplaceArrangement | null>(null);

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
        router.get(WorkplaceArrangementController.index.url(), data, {
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

    const startEdit = (workplaceArrangement: WorkplaceArrangement) => {
        setEditingWorkplaceArrangement(workplaceArrangement);
        form.setData({
            name: workplaceArrangement.name,
            slug: workplaceArrangement.slug,
            description: workplaceArrangement.description || '',
            sort_order: workplaceArrangement.sort_order,
            is_active: workplaceArrangement.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingWorkplaceArrangement(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingWorkplaceArrangement) {
            form.put(
                WorkplaceArrangementController.update.url(
                    editingWorkplaceArrangement.id,
                ),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        cancelEdit();
                    },
                },
            );
        } else {
            form.post(WorkplaceArrangementController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                    form.setData('sort_order', 0);
                },
            });
        }
    };

    const handleDelete = (workplaceArrangement: WorkplaceArrangement) => {
        if (
            confirm(
                `Are you sure you want to delete workplace arrangement "${workplaceArrangement.name}"?`,
            )
        ) {
            router.delete(
                WorkplaceArrangementController.destroy.url(
                    workplaceArrangement.id,
                ),
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="workplace-arrangement-name">Name</Label>
                <Input
                    id="workplace-arrangement-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Remote"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="workplace-arrangement-slug">Slug</Label>
                <Input
                    id="workplace-arrangement-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. remote"
                    maxLength={100}
                    disabled={form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    Unique kebab-case identifier, e.g. remote
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="workplace-arrangement-description">
                    Description
                </Label>
                <Textarea
                    id="workplace-arrangement-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this workplace arrangement"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="workplace-arrangement-sort-order">
                    Display order
                </Label>
                <Input
                    id="workplace-arrangement-sort-order"
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
                    id="workplace-arrangement-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="workplace-arrangement-is-active"
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
                    {editingWorkplaceArrangement
                        ? 'Update Workplace Arrangement'
                        : 'Create Workplace Arrangement'}
                </Button>
                {editingWorkplaceArrangement && (
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
                    <Label htmlFor="workplace-arrangement-search">
                        Search workplace arrangements
                    </Label>
                    <Input
                        id="workplace-arrangement-search"
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
                                <th className="px-4 py-3">Arrangement</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Order</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {workplaceArrangements.data.map(
                                (workplaceArrangement) => (
                                    <tr
                                        key={workplaceArrangement.id}
                                        className="hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-2">
                                                {workplaceArrangement.name}
                                            </div>
                                            {workplaceArrangement.description && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {
                                                        workplaceArrangement.description
                                                    }
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {workplaceArrangement.slug}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {workplaceArrangement.sort_order}
                                        </td>
                                        <td className="px-4 py-3">
                                            {workplaceArrangement.is_active ? (
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
                                            {canManageWorkplaceArrangements && (
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            startEdit(
                                                                workplaceArrangement,
                                                            )
                                                        }
                                                        title="Edit workplace arrangement"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                        onClick={() =>
                                                            handleDelete(
                                                                workplaceArrangement,
                                                            )
                                                        }
                                                        title="Delete workplace arrangement"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ),
                            )}
                            {workplaceArrangements.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No workplace arrangements found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {workplaceArrangements.total > 0 && (
                <Pagination
                    currentPage={workplaceArrangements.current_page}
                    lastPage={workplaceArrangements.last_page}
                    perPage={workplaceArrangements.per_page}
                    total={workplaceArrangements.total}
                    prevPageUrl={workplaceArrangements.prev_page_url}
                    nextPageUrl={workplaceArrangements.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Workplace Arrangements Management" />
            <SplitLayout
                title="Workplace Arrangements"
                description="Manage workplace arrangements used for job postings."
                formTitle={
                    editingWorkplaceArrangement
                        ? `Edit Workplace Arrangement: ${editingWorkplaceArrangement.name}`
                        : 'Create New Workplace Arrangement'
                }
                formDescription="Configure workplace arrangement details."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}