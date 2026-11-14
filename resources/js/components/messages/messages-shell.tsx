import type { ReactNode } from 'react';
import ConversationList from '@/components/messages/conversation-list';
import { cn } from '@/lib/utils';
import type { ConversationsPaginated } from '@/types';

type MessagesShellProps = {
    conversations: ConversationsPaginated;
    unreadTotal: number;
    activeId?: number;
    /** Which pane is visible below the `lg` breakpoint. */
    mobilePane: 'list' | 'thread';
    children: ReactNode;
};

export default function MessagesShell({
    conversations,
    unreadTotal,
    activeId,
    mobilePane,
    children,
}: MessagesShellProps) {
    return (
        <div className="flex h-[calc(100svh-4rem)] min-h-0 w-full bg-background">
            <aside
                className={cn(
                    'h-full w-full shrink-0 flex-col border-r border-border lg:flex lg:w-96 xl:w-[26rem]',
                    mobilePane === 'list' ? 'flex' : 'hidden',
                )}
            >
                <ConversationList
                    conversations={conversations}
                    unreadTotal={unreadTotal}
                    activeId={activeId}
                />
            </aside>
            <section
                className={cn(
                    'h-full min-w-0 flex-1 flex-col',
                    mobilePane === 'thread' ? 'flex' : 'hidden lg:flex',
                )}
            >
                {children}
            </section>
        </div>
    );
}
