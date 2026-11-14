import { Head, Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Card, CardContent } from '@/components/ui/card';
import type { MessagesIndexProps } from '@/types';

export default function MessagesIndex({
    conversations,
    unread_total,
}: MessagesIndexProps) {
    const items = conversations.data;

    return (
        <div className="container mx-auto max-w-4xl px-4 py-8">
            <Head title="Messages" />

            <div className="mb-8">
                <h1 className="text-3xl font-bold tracking-tight">Messages</h1>
                <p className="mt-1 text-muted-foreground">
                    Conversations with candidates and employers about your
                    applications.
                    {unread_total > 0 && (
                        <span className="ml-2 inline-flex items-center rounded-full bg-primary px-2 py-0.5 align-middle text-xs font-medium text-primary-foreground">
                            {unread_total} unread
                        </span>
                    )}
                </p>
            </div>

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <MessageSquare className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h2 className="text-lg font-semibold">
                            No conversations yet
                        </h2>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Conversations start from an application page — open
                            the application and choose Messages.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {items.map((conversation) => {
                        const preview = conversation.last_message
                            ? (conversation.last_message.body ??
                              'This message was deleted')
                            : 'No messages yet';

                        return (
                            <Link
                                key={conversation.id}
                                href={`/messages/${conversation.id}`}
                                className="block"
                            >
                                <Card
                                    className={
                                        conversation.unread
                                            ? 'border-primary/40 bg-primary/5 transition-colors hover:border-primary/60'
                                            : 'transition-colors hover:border-primary/40'
                                    }
                                >
                                    <CardContent className="flex items-start gap-4 p-5">
                                        <div className="flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="text-base font-semibold">
                                                    {conversation.job.title}
                                                </h2>
                                                {conversation.unread && (
                                                    <span className="inline-flex items-center rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">
                                                        Unread
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {conversation.viewer_role ===
                                                'candidate'
                                                    ? `Employer · ${conversation.company.name}`
                                                    : `Candidate · ${conversation.other_party_name}`}
                                            </p>
                                            <p className="line-clamp-1 text-sm text-muted-foreground">
                                                {preview}
                                            </p>
                                        </div>
                                        {conversation.last_message_at && (
                                            <time
                                                dateTime={
                                                    conversation.last_message_at
                                                }
                                                className="shrink-0 text-xs text-muted-foreground"
                                            >
                                                {new Date(
                                                    conversation.last_message_at,
                                                ).toLocaleString()}
                                            </time>
                                        )}
                                    </CardContent>
                                </Card>
                            </Link>
                        );
                    })}

                    {conversations.last_page > 1 && (
                        <div className="mt-8">
                            <Pagination
                                currentPage={conversations.current_page}
                                lastPage={conversations.last_page}
                                perPage={conversations.per_page}
                                total={conversations.total}
                                prevPageUrl={conversations.prev_page_url}
                                nextPageUrl={conversations.next_page_url}
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
