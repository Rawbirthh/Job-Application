import type { InertiaLinkProps } from '@inertiajs/react';
import {
    LayoutGrid,
    Users,
    Layers,
    Briefcase,
    MapPin,
    GraduationCap,
    Clock,
    Banknote,
    Building2,
    Bookmark,
    FileText,
    Bell,
    ClipboardList,
    ListChecks,
    type LucideIcon,
} from 'lucide-react';
import admin from '@/routes/admin';
import employer from '@/routes/employer';
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
        title: 'Browse Jobs',
        href: '/jobs',
        icon: Bookmark,
        requiredRoles: ['job_seeker'],
    },
    {
        title: 'Saved Jobs',
        href: '/candidate/saved-jobs',
        icon: Bookmark,
        requiredRoles: ['job_seeker'],
    },
    {
        title: 'My Applications',
        href: '/candidate/applications',
        icon: FileText,
        requiredRoles: ['job_seeker'],
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
    },
    {
        title: 'Company Profiles',
        href: employer.companies.index(),
        icon: Building2,
        requiredRoles: ['employer'],
    },
    {
        title: 'Jobs & Applicants',
        href: employer.jobs.index(),
        icon: Briefcase,
        requiredRoles: ['employer'],
    },
    {
        title: 'Screening Questions',
        href: employer.screeningQuestions.index(),
        icon: ListChecks,
        requiredRoles: ['employer'],
    },
    {
        title: 'Screening Templates',
        href: employer.screeningTemplates.index(),
        icon: ClipboardList,
        requiredRoles: ['employer'],
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
    {
        title: 'Employment Types',
        href: admin.employmentTypes.index(),
        icon: Briefcase,
        requiredPermissions: ['admin.access', 'employment_types.view'],
    },
    {
        title: 'Workplace Arrangements',
        href: admin.workplaceArrangements.index(),
        icon: MapPin,
        requiredPermissions: ['admin.access', 'workplace_arrangements.view'],
    },
    {
        title: 'Experience Levels',
        href: admin.experienceLevels.index(),
        icon: GraduationCap,
        requiredPermissions: ['admin.access', 'experience_levels.view'],
    },
    {
        title: 'Pay Periods',
        href: admin.payPeriods.index(),
        icon: Clock,
        requiredPermissions: ['admin.access', 'pay_periods.view'],
    },
    {
        title: 'Salary Bands',
        href: admin.salaryBands.index(),
        icon: Banknote,
        requiredPermissions: ['admin.access', 'salary_bands.view'],
    },
    {
        title: 'Employers',
        href: admin.employers.index(),
        icon: Users,
        requiredPermissions: ['admin.access', 'employers.view'],
    },
    {
        title: 'Job Moderation',
        href: admin.jobs.index(),
        icon: Briefcase,
        requiredPermissions: ['admin.access', 'jobs.moderate'],
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
