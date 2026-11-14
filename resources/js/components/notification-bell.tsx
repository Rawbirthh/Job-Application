import { router, usePage } from '@inertiajs/react';
import { Bell, Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificationItem } from '@/types';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';

type BellPageProps = {
    notifications_recent?: NotificationItem[];
    notifications_unread_count?: number;
};

export function NotificationBell() {
    useFlashToast();

    const page = usePage<BellPageProps>();
    const notifications = page.props.notifications_recent ?? [];
    const unreadCount = page.props.notifications_unread_count ?? 0;
    const [isProcessing, setIsProcessing] = useState(false);

    const handleNotificationClick = (notification: NotificationItem) => {
        setIsProcessing(true);
        // Idempotent: the server marks the notification as read (if unread)
        // and redirects to the validated target URL — for both read and
        // unread items.
        router.patch(
            `/notifications/${notification.id}/read`,
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleMarkAllAsRead = () => {
        setIsProcessing(true);
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="relative inline-flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Notifications"
                >
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-xs font-medium text-destructive-foreground">
                            {unreadCount}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(24rem,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                    <p className="text-sm font-semibold">Notifications</p>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 gap-1 px-2 text-xs"
                            onClick={handleMarkAllAsRead}
                            disabled={isProcessing}
                        >
                            <Check className="size-3.5" aria-hidden="true" />
                            Mark all as read
                        </Button>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
                        <div className="rounded-full bg-muted p-3">
                            <Bell
                                className="size-6 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <p className="text-sm font-medium">
                            No notifications yet
                        </p>
                        <p className="max-w-[16rem] text-xs text-muted-foreground">
                            Updates about your applications, jobs, and account
                            will show up here.
                        </p>
                    </div>
                ) : (
                    <ul className="max-h-96 overflow-y-auto py-1">
                        {notifications.map((notification) => {
                            const isUnread = notification.read_at === null;
                            const title =
                                notification.data?.title ?? notification.type;
                            const message = notification.data?.message;

                            return (
                                <li key={notification.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleNotificationClick(
                                                notification,
                                            )
                                        }
                                        disabled={isProcessing}
                                        className={cn(
                                            'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                                            isUnread && 'bg-primary/5',
                                        )}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                isUnread
                                                    ? 'bg-primary'
                                                    : 'bg-transparent',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1 space-y-0.5">
                                            <span className="block text-sm leading-snug font-medium">
                                                {title}
                                            </span>
                                            {message && (
                                                <span className="block text-xs leading-snug break-words text-muted-foreground">
                                                    {message}
                                                </span>
                                            )}
                                            {notification.created_at && (
                                                <span className="block pt-0.5 text-[11px] text-muted-foreground">
                                                    {new Date(
                                                        notification.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
