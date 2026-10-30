import type { InertiaLinkProps } from '@inertiajs/react';
import { LayoutGrid, Users, Layers, type LucideIcon } from 'lucide-react';
import admin from '@/routes/admin';
import { dashboard } from '@/routes';

export type NavItemConfig = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    requiredPermissions?: string[];
    requiredRoles?: string[];
};

export const mainNavConfig: NavItemConfig[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: admin.users.index(),
        icon: Users,
        requiredPermissions: ['admin.access', 'users.view'],
    },
    {
        title: 'Roles',
        href: admin.roles.index(),
        icon: Users,
        requiredPermissions: ['admin.access', 'roles.view'],
    },
    {
        title: 'Permissions',
        href: admin.permissions.index(),
        icon: Users,
        requiredPermissions: ['admin.access', 'permissions.view'],
    },
    {
        title: 'Job Categories',
        href: admin.categories.index(),
        icon: Layers,
        requiredPermissions: ['admin.access', 'categories.view'],
    },
];

export function getFilteredNavItems(
    items: NavItemConfig[],
    roles: string[] = [],
    permissions: string[] = [],
): NavItemConfig[] {
    return items.filter((item) => {
        if (item.requiredRoles && item.requiredRoles.length > 0) {
            const hasRole = item.requiredRoles.some((role) =>
                roles.includes(role),
            );
            if (!hasRole) {
                return false;
            }
        }

        if (item.requiredPermissions && item.requiredPermissions.length > 0) {
            const hasPermission = item.requiredPermissions.every((perm) =>
                permissions.includes(perm),
            );
            if (!hasPermission) {
                return false;
            }
        }

        return true;
    });
}
