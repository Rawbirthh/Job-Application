import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<{ notifications_unread_count?: number }>();
    const unreadCount = page.props.notifications_unread_count ?? 0;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto">
                <Link
                    href="/notifications"
                    className="relative inline-flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Notifications"
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-xs font-medium text-destructive-foreground">
                            {unreadCount}
                        </span>
                    )}
                </Link>
            </div>
        </header>
    );
}
