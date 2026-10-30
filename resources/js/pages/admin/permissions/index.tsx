import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Search, ShieldCheck, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import SplitLayout from '@/components/admin/split-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import type { PaginatedData, PermissionWithRoles } from '@/types/authorization';

type AdminPermissionsIndexProps = {
    permissions: PaginatedData<PermissionWithRoles>;
    filters: {
        search: string;
    };
    canManagePermissions: boolean;
};

export default function AdminPermissionsIndex({
    permissions,
    filters,
    canManagePermissions,
}: AdminPermissionsIndexProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingPermission, setEditingPermission] = useState<PermissionWithRoles | null>(null);

    const form = useForm<{
        name: string;
        slug: string;
        description: string;
    }>({
        name: '',
        slug: '',
        description: '',
    });

    const visitIndex = (data: { search?: string }) => {
        router.get(PermissionController.index.url(), data, {
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

    const startEdit = (permission: PermissionWithRoles) => {
        setEditingPermission(permission);
        form.setData({
            name: permission.name,
            slug: permission.slug,
            description: permission.description || '',
        });
    };

    const cancelEdit = () => {
        setEditingPermission(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingPermission) {
            form.put(PermissionController.update.url(editingPermission.id), {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            });
        } else {
            form.post(PermissionController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                },
            });
        }
    };

    const handleDelete = (permission: PermissionWithRoles) => {
        if (confirm(`Are you sure you want to delete permission "${permission.name}"?`)) {
            router.delete(PermissionController.destroy.url(permission.id), {
                preserveScroll: true,
            });
        }
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="permission-name">Name</Label>
                <Input
                    id="permission-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. View Reports"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="permission-slug">Slug</Label>
                <Input
                    id="permission-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. reports.view"
                    maxLength={100}
                    disabled={Boolean(editingPermission) || form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    {editingPermission
                        ? 'Slug cannot be modified after creation.'
                        : 'kebab-case with dots allowed, e.g. jobs.publish'}
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="permission-description">Description</Label>
                <Textarea
                    id="permission-description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="Optional description of this permission"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex items-center gap-2 pt-2">
                <Button type="submit" disabled={form.processing} className="flex-1">
                    {form.processing && <Spinner className="mr-2" />}
                    {editingPermission ? 'Update Permission' : 'Create Permission'}
                </Button>
                {editingPermission && (
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
                    <Label htmlFor="permission-search">Search permissions</Label>
                    <Input
                        id="permission-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name or slug"
                        maxLength={100}
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={isSearching} variant="secondary">
                        {isSearching ? <Spinner /> : <Search className="size-4" />}
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
                        <thead className="border-b border-border bg-muted/50 text-xs font-medium uppercase text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Permission</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Assigned Roles</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {permissions.data.map((permission) => (
                                <tr key={permission.id} className="hover:bg-muted/40">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {permission.name}
                                            {permission.is_system && (
                                                <Badge variant="outline" className="gap-1">
                                                    <ShieldCheck className="size-3" />
                                                    System
                                                </Badge>
                                            )}
                                        </div>
                                        {permission.description && (
                                            <p className="text-xs text-muted-foreground mt-0.5">
                                                {permission.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {permission.slug}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {permission.roles_count}{' '}
                                            {permission.roles_count === 1 ? 'role' : 'roles'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManagePermissions && !permission.is_system && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => startEdit(permission)}
                                                    title="Edit permission"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() => handleDelete(permission)}
                                                    title="Delete permission"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                        {permission.is_system && (
                                            <span className="text-xs text-muted-foreground">Locked</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {permissions.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-12 text-center text-muted-foreground">
                                        No permissions found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {permissions.total > 0 && (
                <Pagination
                    currentPage={permissions.current_page}
                    lastPage={permissions.last_page}
                    perPage={permissions.per_page}
                    total={permissions.total}
                    prevPageUrl={permissions.prev_page_url}
                    nextPageUrl={permissions.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Permission Management" />
            <SplitLayout
                title="Permission Management"
                description="Create and manage permissions in the system."
                formTitle={editingPermission ? `Edit Permission: ${editingPermission.name}` : 'Create New Permission'}
                formDescription="Configure permission details."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}
