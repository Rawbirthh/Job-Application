import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Search, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import UserRoleController from '@/actions/App/Http/Controllers/Admin/UserRoleController';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { AdminUserIndexProps } from '@/types';

export default function AdminUsersIndex({
    users,
    roles,
    filters,
    canManageRoles,
}: AdminUserIndexProps) {
    const { errors } = usePage<{ errors: { search?: string; role?: string } }>()
        .props;
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(
        filters.role !== null ? String(filters.role) : 'all',
    );
    const [isSearching, setIsSearching] = useState(false);

    const searchError = errors.search;
    const roleError = errors.role;

    const hasSearch = filters.search !== '';
    const hasRoleFilter = filters.role !== null;
    const hasActiveFilters = hasSearch || hasRoleFilter;

    const selectedRole =
        filters.role !== null
            ? roles.find((option) => option.id === filters.role)
            : undefined;

    const buildVisitData = (searchTerm: string, roleValue: string) => {
        const data: { search?: string; role?: number } = {};
        if (searchTerm !== '') {
            data.search = searchTerm;
        }
        if (roleValue !== 'all') {
            data.role = Number.parseInt(roleValue, 10);
        }
        return data;
    };

    const visitIndex = (data: { search?: string; role?: number }) => {
        router.get(UserRoleController.index.url(), data, {
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

        visitIndex(buildVisitData(term, role));
    };

    const handleRoleChange = (value: string) => {
        setRole(value);

        visitIndex(buildVisitData(search.trim(), value));
    };

    const handleClearFilters = () => {
        setSearch('');
        setRole('all');
        visitIndex({});
    };

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Users
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Search the user directory and manage global role
                            assignments.
                        </p>
                    </div>

                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {users.total} {users.total === 1 ? 'user' : 'users'}
                        {hasSearch && <> matching “{filters.search}”</>}
                        {hasRoleFilter && (
                            <>
                                {' '}
                                with role{' '}
                                {selectedRole?.name ?? `#${filters.role}`}
                            </>
                        )}
                    </p>
                </div>

                <form
                    role="search"
                    aria-label="Filter users"
                    onSubmit={handleSearch}
                    aria-busy={isSearching}
                    className="flex w-fit flex-wrap items-end gap-2"
                >
                    <div className="grid w-full gap-1.5 sm:w-72">
                        <Label htmlFor="user-search">Search users</Label>
                        <Input
                            id="user-search"
                            name="search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name or email prefix"
                            autoComplete="off"
                            maxLength={100}
                            aria-invalid={searchError ? true : undefined}
                            aria-describedby={
                                searchError ? 'user-search-error' : undefined
                            }
                        />
                        <InputError
                            id="user-search-error"
                            message={searchError}
                        />
                    </div>

                    <div className="grid w-full gap-1.5 sm:w-52">
                        <Label htmlFor="user-role">Role</Label>
                        <Select value={role} onValueChange={handleRoleChange}>
                            <SelectTrigger
                                id="user-role"
                                aria-invalid={roleError ? true : undefined}
                                aria-describedby={
                                    roleError ? 'user-role-error' : undefined
                                }
                            >
                                <SelectValue placeholder="All roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All roles</SelectItem>
                                {roles.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError id="user-role-error" message={roleError} />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={isSearching}>
                            {isSearching ? (
                                <Spinner />
                            ) : (
                                <Search aria-hidden="true" />
                            )}
                            {isSearching ? 'Searching…' : 'Search'}
                        </Button>

                        {hasActiveFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleClearFilters}
                            >
                                Clear filters
                            </Button>
                        )}
                    </div>
                </form>

                <div className="overflow-hidden rounded-lg border border-border">
                    <div
                        role="region"
                        aria-label="User directory table"
                        aria-busy={isSearching}
                        tabIndex={0}
                        className="overflow-x-auto focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                    >
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <caption className="sr-only">
                                User accounts and their global roles
                            </caption>

                            <thead className="border-b border-border bg-muted/50 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Name
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Email
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Roles
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
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="align-top transition-colors hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-3 break-words text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            {user.roles.length > 0 ? (
                                                <ul
                                                    className="flex flex-wrap gap-1.5"
                                                    aria-label={`Roles for ${user.name}`}
                                                >
                                                    {user.roles.map((role) => (
                                                        <li key={role.id}>
                                                            <Badge variant="secondary">
                                                                {role.name}
                                                            </Badge>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    No roles
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {canManageRoles ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={UserRoleController.edit.url(
                                                            user.id,
                                                        )}
                                                        aria-label={`Manage roles for ${user.name}`}
                                                        className="whitespace-nowrap"
                                                    >
                                                        Manage roles
                                                        <ChevronRight aria-hidden="true" />
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                    View only
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-12">
                                            <div className="mx-auto flex max-w-md flex-col items-center gap-2 text-center">
                                                <div
                                                    className="mb-1 flex size-12 items-center justify-center rounded-full bg-muted"
                                                    aria-hidden="true"
                                                >
                                                    {hasActiveFilters ? (
                                                        <Search className="size-6 text-muted-foreground" />
                                                    ) : (
                                                        <Users className="size-6 text-muted-foreground" />
                                                    )}
                                                </div>

                                                <p className="font-medium">
                                                    {hasActiveFilters
                                                        ? 'No matching users'
                                                        : 'No users yet'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {hasActiveFilters
                                                        ? 'No users match the current filters. Try a different name, email prefix, or role.'
                                                        : 'User accounts will appear here once they are created.'}
                                                </p>

                                                {hasActiveFilters && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={
                                                            handleClearFilters
                                                        }
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

                {users.total > 0 && (
                    <Pagination
                        currentPage={users.current_page}
                        lastPage={users.last_page}
                        perPage={users.per_page}
                        total={users.total}
                        prevPageUrl={users.prev_page_url}
                        nextPageUrl={users.next_page_url}
                    />
                )}
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: UserRoleController.index.url(),
        },
    ],
};
