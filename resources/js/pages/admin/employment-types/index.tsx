import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import EmploymentTypeController from '@/actions/App/Http/Controllers/Admin/EmploymentTypeController';
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

export type EmploymentType = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

type AdminEmploymentTypesIndexProps = {
    employmentTypes: PaginatedData<EmploymentType>;
    filters: {
        search: string;
    };
    canManageEmploymentTypes: boolean;
};

export default function AdminEmploymentTypesIndex({
    employmentTypes,
    filters,
    canManageEmploymentTypes,
}: AdminEmploymentTypesIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingEmploymentType, setEditingEmploymentType] =
        useState<EmploymentType | null>(null);

    const form = useForm<{
        name: string;
        slug: string;
        description: string;
        is_active: boolean;
    }>({
        name: '',
        slug: '',
        description: '',
        is_active: true,
    });

    const visitIndex = (data: { search?: string }) => {
        router.get(EmploymentTypeController.index.url(), data, {
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

    const startEdit = (employmentType: EmploymentType) => {
        setEditingEmploymentType(employmentType);
        form.setData({
            name: employmentType.name,
            slug: employmentType.slug,
            description: employmentType.description || '',
            is_active: employmentType.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingEmploymentType(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingEmploymentType) {
            form.put(
                EmploymentTypeController.update.url(editingEmploymentType.id),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        cancelEdit();
                    },
                },
            );
        } else {
            form.post(EmploymentTypeController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                },
            });
        }
    };

    const handleDelete = (employmentType: EmploymentType) => {
        if (
            confirm(
                `Are you sure you want to delete employment type "${employmentType.name}"?`,
            )
        ) {
            router.delete(
                EmploymentTypeController.destroy.url(employmentType.id),
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="employment-type-name">Name</Label>
                <Input
                    id="employment-type-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Full-time"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="employment-type-slug">Slug</Label>
                <Input
                    id="employment-type-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. full-time"
                    maxLength={100}
                    disabled={form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    Unique kebab-case identifier, e.g. part-time
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="employment-type-description">Description</Label>
                <Textarea
                    id="employment-type-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this employment type"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex items-center space-x-2 pt-2">
                <Checkbox
                    id="employment-type-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="employment-type-is-active"
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
                    {editingEmploymentType
                        ? 'Update Employment Type'
                        : 'Create Employment Type'}
                </Button>
                {editingEmploymentType && (
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
                    <Label htmlFor="employment-type-search">
                        Search employment types
                    </Label>
                    <Input
                        id="employment-type-search"
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
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {employmentTypes.data.map((employmentType) => (
                                <tr
                                    key={employmentType.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {employmentType.name}
                                        </div>
                                        {employmentType.description && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {employmentType.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {employmentType.slug}
                                    </td>
                                    <td className="px-4 py-3">
                                        {employmentType.is_active ? (
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
                                        {canManageEmploymentTypes && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(
                                                            employmentType,
                                                        )
                                                    }
                                                    title="Edit employment type"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(
                                                            employmentType,
                                                        )
                                                    }
                                                    title="Delete employment type"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {employmentTypes.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No employment types found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {employmentTypes.total > 0 && (
                <Pagination
                    currentPage={employmentTypes.current_page}
                    lastPage={employmentTypes.last_page}
                    perPage={employmentTypes.per_page}
                    total={employmentTypes.total}
                    prevPageUrl={employmentTypes.prev_page_url}
                    nextPageUrl={employmentTypes.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Employment Types Management" />
            <SplitLayout
                title="Employment Types"
                description="Manage employment types used for job postings."
                formTitle={
                    editingEmploymentType
                        ? `Edit Employment Type: ${editingEmploymentType.name}`
                        : 'Create New Employment Type'
                }
                formDescription="Configure employment type details."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}
