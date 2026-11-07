import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Bell } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { NotificationsIndexProps } from '@/types';

export default function NotificationsIndex({
    notifications,
    pagination,
}: NotificationsIndexProps) {
    useFlashToast();

    const items = Array.isArray(notifications)
        ? notifications
        : notifications.data;
    const pager =
        pagination ?? (Array.isArray(notifications) ? null : notifications);

    const page = usePage<{ notifications_unread_count?: number }>();
    const [isProcessing, setIsProcessing] = useState(false);
    const hasUnreadGlobally = (page.props.notifications_unread_count ?? 0) > 0;

    const handleMarkAsRead = (id: string) => {
        setIsProcessing(true);
        router.patch(
            `/notifications/${id}/read`,
            {},
            {
                preserveScroll: true,
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
        <div className="container mx-auto max-w-4xl px-4 py-8">
            <Head title="Notifications" />

            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Notifications
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        Your in-app notifications and updates.
                    </p>
                </div>
                {hasUnreadGlobally && (
                    <Button
                        variant="outline"
                        onClick={handleMarkAllAsRead}
                        disabled={isProcessing}
                    >
                        Mark all as read
                    </Button>
                )}
            </div>

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Bell className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            No notifications yet
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            You will see updates about your applications, jobs,
                            and account here.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {items.map((notification) => {
                        const isUnread = notification.read_at === null;
                        const title =
                            notification.data?.title ?? notification.type;
                        const message = notification.data?.message;
                        const url = notification.data?.url;

                        return (
                            <Card
                                key={notification.id}
                                className={
                                    isUnread
                                        ? 'border-primary/40 bg-primary/5'
                                        : ''
                                }
                            >
                                <CardContent className="flex flex-col gap-3 p-5 md:flex-row md:items-start md:justify-between">
                                    <div className="flex-1 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-base font-semibold">
                                                {title}
                                            </h2>
                                            {isUnread && (
                                                <span className="inline-flex items-center rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                                                    Unread
                                                </span>
                                            )}
                                        </div>
                                        {message && (
                                            <p className="text-sm text-muted-foreground">
                                                {message}
                                            </p>
                                        )}
                                        {notification.created_at && (
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(
                                                    notification.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 self-end md:self-start">
                                        {url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={url}>View</Link>
                                            </Button>
                                        )}
                                        {isUnread && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    handleMarkAsRead(
                                                        notification.id,
                                                    )
                                                }
                                                disabled={isProcessing}
                                            >
                                                Mark as read
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {pager && pager.last_page > 1 && (
                        <div className="mt-8">
                            <Pagination
                                currentPage={pager.current_page}
                                lastPage={pager.last_page}
                                perPage={pager.per_page}
                                total={pager.total}
                                prevPageUrl={pager.prev_page_url}
                                nextPageUrl={pager.next_page_url}
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
