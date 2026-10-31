import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ExperienceLevelController from '@/actions/App/Http/Controllers/Admin/ExperienceLevelController';
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

export type ExperienceLevel = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
};

type AdminExperienceLevelsIndexProps = {
    experienceLevels: PaginatedData<ExperienceLevel>;
    filters: {
        search: string;
    };
    canManageExperienceLevels: boolean;
};

export default function AdminExperienceLevelsIndex({
    experienceLevels,
    filters,
    canManageExperienceLevels,
}: AdminExperienceLevelsIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingExperienceLevel, setEditingExperienceLevel] =
        useState<ExperienceLevel | null>(null);

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
        router.get(ExperienceLevelController.index.url(), data, {
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

    const startEdit = (experienceLevel: ExperienceLevel) => {
        setEditingExperienceLevel(experienceLevel);
        form.setData({
            name: experienceLevel.name,
            slug: experienceLevel.slug,
            description: experienceLevel.description || '',
            sort_order: experienceLevel.sort_order,
            is_active: experienceLevel.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingExperienceLevel(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingExperienceLevel) {
            form.put(
                ExperienceLevelController.update.url(editingExperienceLevel.id),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        cancelEdit();
                    },
                },
            );
        } else {
            form.post(ExperienceLevelController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                    form.setData('sort_order', 0);
                },
            });
        }
    };

    const handleDelete = (experienceLevel: ExperienceLevel) => {
        if (
            confirm(
                `Are you sure you want to delete experience level "${experienceLevel.name}"?`,
            )
        ) {
            router.delete(
                ExperienceLevelController.destroy.url(experienceLevel.id),
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="experience-level-name">Name</Label>
                <Input
                    id="experience-level-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Senior"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="experience-level-slug">Slug</Label>
                <Input
                    id="experience-level-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. senior"
                    maxLength={100}
                    disabled={form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    Unique machine identifier (letters, numbers, dashes,
                    underscores), e.g. senior
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="experience-level-description">
                    Description
                </Label>
                <Textarea
                    id="experience-level-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this experience level"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="experience-level-sort-order">
                    Display order
                </Label>
                <Input
                    id="experience-level-sort-order"
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
                    id="experience-level-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="experience-level-is-active"
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
                    {editingExperienceLevel
                        ? 'Update Experience Level'
                        : 'Create Experience Level'}
                </Button>
                {editingExperienceLevel && (
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
                    <Label htmlFor="experience-level-search">
                        Search experience levels
                    </Label>
                    <Input
                        id="experience-level-search"
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
                                    Level
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Slug
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    Order
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
                            {experienceLevels.data.map((experienceLevel) => (
                                <tr
                                    key={experienceLevel.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {experienceLevel.name}
                                        </div>
                                        {experienceLevel.description && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {experienceLevel.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {experienceLevel.slug}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {experienceLevel.sort_order}
                                    </td>
                                    <td className="px-4 py-3">
                                        {experienceLevel.is_active ? (
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
                                        {canManageExperienceLevels && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(
                                                            experienceLevel,
                                                        )
                                                    }
                                                    title="Edit experience level"
                                                    aria-label={`Edit ${experienceLevel.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(
                                                            experienceLevel,
                                                        )
                                                    }
                                                    title="Delete experience level"
                                                    aria-label={`Delete ${experienceLevel.name}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {experienceLevels.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={5}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No experience levels found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {experienceLevels.total > 0 && (
                <Pagination
                    currentPage={experienceLevels.current_page}
                    lastPage={experienceLevels.last_page}
                    perPage={experienceLevels.per_page}
                    total={experienceLevels.total}
                    prevPageUrl={experienceLevels.prev_page_url}
                    nextPageUrl={experienceLevels.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Experience Levels Management" />
            <SplitLayout
                title="Experience Levels"
                description="Manage experience levels used for job postings."
                formTitle={
                    canManageExperienceLevels
                        ? editingExperienceLevel
                            ? `Edit Experience Level: ${editingExperienceLevel.name}`
                            : 'Create New Experience Level'
                        : 'View-only access'
                }
                formDescription={
                    canManageExperienceLevels
                        ? 'Configure experience level details.'
                        : 'You can browse the catalogue but cannot create, edit, or delete levels.'
                }
                form={
                    canManageExperienceLevels ? (
                        formContent
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Your role has view-only access to experience levels.
                        </p>
                    )
                }
                table={tableContent}
            />
        </>
    );
}
