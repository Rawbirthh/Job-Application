import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, Trash2, CheckCircle2, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import JobCategoryController from '@/actions/App/Http/Controllers/Admin/JobCategoryController';
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

export type JobCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

type AdminJobCategoriesIndexProps = {
    categories: PaginatedData<JobCategory>;
    filters: {
        search: string;
    };
    canManageCategories: boolean;
};

export default function AdminJobCategoriesIndex({
    categories,
    filters,
    canManageCategories,
}: AdminJobCategoriesIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingCategory, setEditingCategory] = useState<JobCategory | null>(null);

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
        router.get(JobCategoryController.index.url(), data, {
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

    const startEdit = (category: JobCategory) => {
        setEditingCategory(category);
        form.setData({
            name: category.name,
            slug: category.slug,
            description: category.description || '',
            is_active: category.is_active,
        });
    };

    const cancelEdit = () => {
        setEditingCategory(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingCategory) {
            form.put(JobCategoryController.update.url(editingCategory.id), {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            });
        } else {
            form.post(JobCategoryController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.setData('is_active', true);
                },
            });
        }
    };

    const handleDelete = (category: JobCategory) => {
        if (
            confirm(
                `Are you sure you want to delete job category "${category.name}"?`,
            )
        ) {
            router.delete(JobCategoryController.destroy.url(category.id), {
                preserveScroll: true,
            });
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="category-name">Name</Label>
                <Input
                    id="category-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Engineering"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="category-slug">Slug</Label>
                <Input
                    id="category-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. engineering"
                    maxLength={100}
                    disabled={form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    Unique kebab-case identifier, e.g. software-engineering
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="category-description">Description</Label>
                <Textarea
                    id="category-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this job category"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex items-center space-x-2 pt-2">
                <Checkbox
                    id="category-is-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', Boolean(checked))
                    }
                    disabled={form.processing}
                />
                <Label
                    htmlFor="category-is-active"
                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
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
                    {editingCategory
                        ? 'Update Category'
                        : 'Create Category'}
                </Button>
                {editingCategory && (
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
                    <Label htmlFor="category-search">
                        Search job categories
                    </Label>
                    <Input
                        id="category-search"
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
                                <th className="px-4 py-3">Category</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {categories.data.map((category) => (
                                <tr
                                    key={category.id}
                                    className="hover:bg-muted/40"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {category.name}
                                        </div>
                                        {category.description && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {category.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {category.slug}
                                    </td>
                                    <td className="px-4 py-3">
                                        {category.is_active ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 text-emerald-600 border-emerald-300 dark:text-emerald-400 dark:border-emerald-800"
                                            >
                                                <CheckCircle2 className="size-3" />
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 text-muted-foreground border-border"
                                            >
                                                <XCircle className="size-3" />
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManageCategories && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(category)
                                                    }
                                                    title="Edit category"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(category)
                                                    }
                                                    title="Delete category"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {categories.data.length === 0 && (
                                <tr key="empty">
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No job categories found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {categories.total > 0 && (
                <Pagination
                    currentPage={categories.current_page}
                    lastPage={categories.last_page}
                    perPage={categories.per_page}
                    total={categories.total}
                    prevPageUrl={categories.prev_page_url}
                    nextPageUrl={categories.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Job Categories Management" />
            <SplitLayout
                title="Job Categories"
                description="Manage job categories used for job postings and discovery."
                formTitle={
                    editingCategory
                        ? `Edit Category: ${editingCategory.name}`
                        : 'Create New Category'
                }
                formDescription="Configure job category details."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}
