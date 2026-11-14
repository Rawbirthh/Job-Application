import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ConversationsPaginated } from '@/types';

type ConversationListProps = {
    conversations: ConversationsPaginated;
    unreadTotal: number;
    activeId?: number;
    className?: string;
};

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    const first = parts[0]?.charAt(0) ?? '';
    const last =
        parts.length > 1 ? (parts[parts.length - 1]?.charAt(0) ?? '') : '';

    return `${first}${last}`.toUpperCase() || '?';
}

function formatTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    const isToday = date.toDateString() === now.toDateString();

    if (isToday) {
        return date.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const sameYear = date.getFullYear() === now.getFullYear();

    return date.toLocaleDateString(
        undefined,
        sameYear
            ? { month: 'short', day: 'numeric' }
            : { year: 'numeric', month: 'short', day: 'numeric' },
    );
}

export default function ConversationList({
    conversations,
    unreadTotal,
    activeId,
    className,
}: ConversationListProps) {
    const items = conversations.data;

    return (
        <div className={cn('flex h-full min-h-0 flex-col', className)}>
            <div className="flex shrink-0 items-center justify-between gap-2 border-b border-border px-4 py-3">
                <div className="flex items-center gap-2">
                    <h1 className="text-base font-semibold">Messages</h1>
                    {unreadTotal > 0 && (
                        <span className="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-xs font-medium text-primary-foreground">
                            {unreadTotal}
                        </span>
                    )}
                </div>
                <span className="text-xs text-muted-foreground">
                    {conversations.total}{' '}
                    {conversations.total === 1 ? 'thread' : 'threads'}
                </span>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-2">
                {items.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
                        <div className="rounded-full bg-muted p-3">
                            <MessageSquare
                                className="size-6 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                        <p className="text-sm font-medium">
                            No conversations yet
                        </p>
                        <p className="max-w-[16rem] text-xs text-muted-foreground">
                            Conversations start from an application page — open
                            the application and choose Messages.
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-1">
                        {items.map((conversation) => {
                            const preview = conversation.last_message
                                ? (conversation.last_message.body ??
                                  'This message was deleted')
                                : 'No messages yet';
                            const isActive = conversation.id === activeId;
                            const name = conversation.other_party_name;

                            return (
                                <li key={conversation.id}>
                                    <Link
                                        href={`/messages/${conversation.id}`}
                                        aria-current={
                                            isActive ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border border-transparent p-2.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            isActive
                                                ? 'border-primary/30 bg-primary/10'
                                                : conversation.unread
                                                  ? 'bg-primary/5 hover:bg-primary/10'
                                                  : 'hover:bg-accent',
                                        )}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                                conversation.unread
                                                    ? 'bg-primary/15 text-primary'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {initials(name)}
                                        </span>
                                        <span className="min-w-0 flex-1 space-y-0.5">
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span
                                                    className={cn(
                                                        'truncate text-sm leading-snug',
                                                        conversation.unread
                                                            ? 'font-semibold'
                                                            : 'font-medium',
                                                    )}
                                                >
                                                    {name}
                                                </span>
                                                {conversation.last_message_at && (
                                                    <time
                                                        dateTime={
                                                            conversation.last_message_at
                                                        }
                                                        className="shrink-0 text-[11px] text-muted-foreground"
                                                    >
                                                        {formatTime(
                                                            conversation.last_message_at,
                                                        )}
                                                    </time>
                                                )}
                                            </span>
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {conversation.job.title}
                                                </span>
                                                {conversation.unread && (
                                                    <span
                                                        aria-hidden="true"
                                                        className="size-2 shrink-0 rounded-full bg-primary"
                                                    />
                                                )}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {preview}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {conversations.last_page > 1 && (
                <div className="flex shrink-0 items-center justify-between gap-2 border-t border-border px-4 py-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 text-xs"
                        asChild={conversations.prev_page_url !== null}
                        disabled={conversations.prev_page_url === null}
                    >
                        {conversations.prev_page_url ? (
                            <Link href={conversations.prev_page_url} rel="prev">
                                <ChevronLeft className="size-3.5" />
                                Newer
                            </Link>
                        ) : (
                            <>
                                <ChevronLeft className="size-3.5" />
                                Newer
                            </>
                        )}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Page{' '}
                        <span className="font-medium text-foreground">
                            {conversations.current_page}
                        </span>{' '}
                        of {conversations.last_page}
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 text-xs"
                        asChild={conversations.next_page_url !== null}
                        disabled={conversations.next_page_url === null}
                    >
                        {conversations.next_page_url ? (
                            <Link href={conversations.next_page_url} rel="next">
                                Older
                                <ChevronRight className="size-3.5" />
                            </Link>
                        ) : (
                            <>
                                Older
                                <ChevronRight className="size-3.5" />
                            </>
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
