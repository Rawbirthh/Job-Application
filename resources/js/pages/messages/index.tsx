import { Head } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import MessagesShell from '@/components/messages/messages-shell';
import type { MessagesIndexProps } from '@/types';

export default function MessagesIndex({
    conversations,
    unread_total,
}: MessagesIndexProps) {
    return (
        <>
            <Head title="Messages" />
            <MessagesShell
                conversations={conversations}
                unreadTotal={unread_total}
                mobilePane="list"
            >
                {/* Desktop empty state: nothing selected */}
                <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                    <div className="rounded-full bg-muted p-5">
                        <MessageSquare
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">
                            Select a conversation
                        </h2>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Pick a thread on the left to read and reply. New
                            conversations start from an application page.
                        </p>
                    </div>
                </div>
            </MessagesShell>
        </>
    );
}
