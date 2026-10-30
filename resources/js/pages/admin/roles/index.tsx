import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Search, ShieldCheck, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
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
import type {
    PaginatedData,
    RoleWithPermissions,
    PermissionSummary,
} from '@/types/authorization';

type AdminRolesIndexProps = {
    roles: PaginatedData<RoleWithPermissions>;
    permissions: PermissionSummary[];
    filters: {
        search: string;
    };
    canManageRoles: boolean;
};

export default function AdminRolesIndex({
    roles,
    permissions,
    filters,
    canManageRoles,
}: AdminRolesIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);
    const [editingRole, setEditingRole] = useState<RoleWithPermissions | null>(
        null,
    );

    const form = useForm<{
        name: string;
        slug: string;
        description: string;
        permissions: number[];
    }>({
        name: '',
        slug: '',
        description: '',
        permissions: [],
    });

    const visitIndex = (data: { search?: string }) => {
        router.get(RoleController.index.url(), data, {
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

    const startEdit = (role: RoleWithPermissions) => {
        setEditingRole(role);
        form.setData({
            name: role.name,
            slug: role.slug,
            description: role.description || '',
            permissions: role.permission_ids || [],
        });
    };

    const cancelEdit = () => {
        setEditingRole(null);
        form.reset();
        form.clearErrors();
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editingRole) {
            form.put(RoleController.update.url(editingRole.id), {
                preserveScroll: true,
                onSuccess: () => {
                    cancelEdit();
                },
            });
        } else {
            form.post(RoleController.store.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                },
            });
        }
    };

    const handleDelete = (role: RoleWithPermissions) => {
        if (confirm(`Are you sure you want to delete role "${role.name}"?`)) {
            router.delete(RoleController.destroy.url(role.id), {
                preserveScroll: true,
            });
        }
    };

    const togglePermission = (permId: number, checked: boolean) => {
        form.setData(
            'permissions',
            checked
                ? Array.from(new Set([...form.data.permissions, permId]))
                : form.data.permissions.filter((id) => id !== permId),
        );
    };

    const formContent = (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="role-name">Name</Label>
                <Input
                    id="role-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Moderator"
                    maxLength={100}
                    disabled={form.processing}
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="role-slug">Slug</Label>
                <Input
                    id="role-slug"
                    value={form.data.slug}
                    onChange={(e) => form.setData('slug', e.target.value)}
                    placeholder="e.g. moderator"
                    maxLength={100}
                    disabled={Boolean(editingRole) || form.processing}
                />
                <p className="text-xs text-muted-foreground">
                    {editingRole
                        ? 'Slug cannot be modified after creation.'
                        : 'kebab-case, e.g. content-moderator'}
                </p>
                <InputError message={form.errors.slug} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="role-description">Description</Label>
                <Textarea
                    id="role-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optional description of this role"
                    maxLength={500}
                    rows={3}
                    disabled={form.processing}
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="space-y-2">
                <Label>Assigned Permissions</Label>
                <div className="max-h-60 space-y-2 overflow-y-auto rounded-md border border-border p-3">
                    {permissions.map((perm) => (
                        <div key={perm.id} className="flex items-start gap-2">
                            <Checkbox
                                id={`perm-${perm.id}`}
                                checked={form.data.permissions.includes(
                                    perm.id,
                                )}
                                onCheckedChange={(checked) =>
                                    togglePermission(perm.id, checked === true)
                                }
                                disabled={form.processing}
                            />
                            <Label
                                htmlFor={`perm-${perm.id}`}
                                className="cursor-pointer text-sm leading-tight font-normal"
                            >
                                <span className="font-medium">{perm.name}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {perm.slug}
                                </span>
                            </Label>
                        </div>
                    ))}
                    {permissions.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No permissions available.
                        </p>
                    )}
                </div>
                <InputError message={form.errors.permissions} />
            </div>

            <div className="flex items-center gap-2 pt-2">
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="flex-1"
                >
                    {form.processing && <Spinner className="mr-2" />}
                    {editingRole ? 'Update Role' : 'Create Role'}
                </Button>
                {editingRole && (
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
                    <Label htmlFor="role-search">Search roles</Label>
                    <Input
                        id="role-search"
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
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Slug</th>
                                <th className="px-4 py-3">Permissions</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {roles.data.map((role) => (
                                <tr key={role.id} className="hover:bg-muted/40">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex items-center gap-2">
                                            {role.name}
                                            {role.is_system && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1"
                                                >
                                                    <ShieldCheck className="size-3" />
                                                    System
                                                </Badge>
                                            )}
                                        </div>
                                        {role.description && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {role.description}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {role.slug}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {role.permissions_count}{' '}
                                            {role.permissions_count === 1
                                                ? 'permission'
                                                : 'permissions'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManageRoles && !role.is_system && (
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        startEdit(role)
                                                    }
                                                    title="Edit role"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    onClick={() =>
                                                        handleDelete(role)
                                                    }
                                                    title="Delete role"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                        {role.is_system && (
                                            <span className="text-xs text-muted-foreground">
                                                Locked
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {roles.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No roles found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {roles.total > 0 && (
                <Pagination
                    currentPage={roles.current_page}
                    lastPage={roles.last_page}
                    perPage={roles.per_page}
                    total={roles.total}
                    prevPageUrl={roles.prev_page_url}
                    nextPageUrl={roles.next_page_url}
                />
            )}
        </>
    );

    return (
        <>
            <Head title="Role Management" />
            <SplitLayout
                title="Role Management"
                description="Create and manage roles and assign permissions."
                formTitle={
                    editingRole
                        ? `Edit Role: ${editingRole.name}`
                        : 'Create New Role'
                }
                formDescription="Configure role details and associated permissions."
                form={formContent}
                table={tableContent}
            />
        </>
    );
}
