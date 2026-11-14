import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Pencil,
    Trash2,
    Check,
    X,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { getEcho } from '@/lib/echo';
import { http } from '@/lib/http';
import { cn } from '@/lib/utils';
import MessagesShell from '@/components/messages/messages-shell';
import type { ConversationMessage, MessagesShowProps } from '@/types';
import type { Auth } from '@/types/auth';

type RealtimeMessage = {
    id: number;
    conversation_id: number;
    sender_id: number;
    sender_name: string;
    body: string | null;
    edited_at?: string | null;
    deleted_at?: string | null;
    created_at: string | null;
};

const MAX_BODY = 2000;

export default function MessagesShow({
    conversation,
    messages,
    conversations,
    unread_total,
}: MessagesShowProps) {
    useFlashToast();

    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user?.id;

    const [items, setItems] = useState<ConversationMessage[]>(messages.data);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteMessageId, setDeleteMessageId] = useState<number | null>(null);
    const listRef = useRef<HTMLDivElement | null>(null);

    const sendForm = useForm({ body: '' });
    const editForm = useForm<{ body: string }>({ body: '' });
    const deleteForm = useForm<Record<string, never>>({});

    // Re-sync when the server sends fresh page props (e.g. after send/edit
    // or after a "Load earlier messages" partial reload). Merge by id so a
    // partial prop containing only the older page never clobbers the visible
    // list — the id-keyed merge is idempotent for both paths.
    useEffect(() => {
        setItems((previous) => {
            const merged = new Map(previous.map((item) => [item.id, item]));

            for (const incoming of messages.data) {
                merged.set(incoming.id, incoming);
            }

            return [...merged.values()].sort((a, b) => a.id - b.id);
        });
    }, [messages.data]);

    // Open the conversation pinned to the latest message.
    useEffect(() => {
        const el = listRef.current;

        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, []);

    const maxIdRef = useRef<number | null>(null);
    const scrollAdjustRef = useRef<number | null>(null);

    // Keep the list pinned to the newest message when it arrives (if the
    // viewer is near the bottom or it is their own message); when older
    // messages are prepended, restore the previous scroll position instead.
    useEffect(() => {
        const el = listRef.current;

        if (!el) {
            return;
        }

        if (scrollAdjustRef.current !== null) {
            el.scrollTop = el.scrollHeight - scrollAdjustRef.current;
            scrollAdjustRef.current = null;
            return;
        }

        const nextMax = items.length > 0 ? items[items.length - 1].id : null;
        const nearBottom =
            el.scrollHeight - el.scrollTop - el.clientHeight < 150;
        const latestIsMine = items[items.length - 1]?.is_mine ?? false;

        if (
            nextMax !== null &&
            (maxIdRef.current === null || nextMax > maxIdRef.current) &&
            (nearBottom || latestIsMine)
        ) {
            el.scrollTop = el.scrollHeight;
        }

        maxIdRef.current = nextMax;
    }, [items]);

    const applicationHref =
        conversation.viewer_role === 'candidate'
            ? `/candidate/applications/${conversation.application_id}`
            : `/employer/applications/${conversation.application_id}`;

    const markRead = () => {
        // Fire-and-forget cursor advance. A plain XHR is used instead of an Inertia
        // visit because the endpoint returns 204; Inertia would show its built-in
        // error dialog for a non-Inertia response.
        void http
            .post(`/messages/${conversation.id}/read`)
            .then(() => refreshInbox())
            .catch(() => undefined);
    };

    // Refresh just the left-hand inbox props (not the thread) after activity
    // that changes the unread badges or last-message previews.
    const refreshInbox = () => {
        router.reload({
            only: ['conversations', 'unread_total'],
        });
    };

    // Real-time subscription (no-op when Reverb is not configured).
    useEffect(() => {
        const echo = getEcho();

        if (!echo) {
            return;
        }

        const channel = echo.private(`conversation.${conversation.id}`);

        const handleSent = (payload: { message: RealtimeMessage }) => {
            const incoming = payload.message;

            setItems((previous) => {
                if (previous.some((item) => item.id === incoming.id)) {
                    return previous;
                }

                const message: ConversationMessage = {
                    id: incoming.id,
                    conversation_id: incoming.conversation_id,
                    sender_id: incoming.sender_id,
                    sender_name: incoming.sender_name,
                    body: incoming.body,
                    edited_at: null,
                    deleted_at: null,
                    created_at: incoming.created_at,
                    is_mine:
                        currentUserId !== undefined &&
                        incoming.sender_id === currentUserId,
                };

                return [...previous, message];
            });

            if (currentUserId && incoming.sender_id !== currentUserId) {
                markRead();
            } else {
                refreshInbox();
            }
        };

        const handleEdited = (payload: {
            message: Pick<
                RealtimeMessage,
                'id' | 'conversation_id' | 'body' | 'edited_at'
            >;
        }) => {
            const { id, body, edited_at } = payload.message;

            setItems((previous) =>
                previous.map((item) =>
                    item.id === id && item.deleted_at === null
                        ? { ...item, body, edited_at: edited_at ?? null }
                        : item,
                ),
            );
            refreshInbox();
        };

        const handleDeleted = (payload: {
            message: Pick<
                RealtimeMessage,
                'id' | 'conversation_id' | 'deleted_at'
            >;
        }) => {
            const { id, deleted_at } = payload.message;

            setItems((previous) =>
                previous.map((item) =>
                    item.id === id
                        ? {
                              ...item,
                              body: null,
                              deleted_at:
                                  deleted_at ?? new Date().toISOString(),
                          }
                        : item,
                ),
            );
            refreshInbox();
        };

        channel
            .listen('.message.sent', handleSent)
            .listen('.message.edited', handleEdited)
            .listen('.message.deleted', handleDeleted);

        return () => {
            echo.leave(`conversation.${conversation.id}`);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [conversation.id, currentUserId]);

    const submitSend = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const body = sendForm.data.body.trim();

        if (body.length === 0) {
            return;
        }

        sendForm.post(`/messages/${conversation.id}/messages`, {
            preserveScroll: true,
            onSuccess: () => sendForm.reset(),
        });
    };

    const handleComposerKeyDown = (
        event: KeyboardEvent<HTMLTextAreaElement>,
    ) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    const startEdit = (message: ConversationMessage) => {
        setEditingId(message.id);
        editForm.setData('body', message.body ?? '');
        editForm.clearErrors();
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const saveEdit = (event: FormEvent<HTMLFormElement>, id: number) => {
        event.preventDefault();

        editForm.put(`/messages/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (deleteMessageId === null) {
            return;
        }

        deleteForm.delete(`/messages/${deleteMessageId}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteMessageId(null),
        });
    };

    const loadEarlier = () => {
        if (!messages.next_page_url || !listRef.current) {
            return;
        }

        scrollAdjustRef.current = listRef.current.scrollHeight;

        router.get(
            messages.next_page_url,
            {},
            {
                only: ['messages'],
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const incoming = (
                        page.props as unknown as MessagesShowProps
                    ).messages.data;

                    setItems((previous) => {
                        const merged = new Map(
                            previous.map((item) => [item.id, item]),
                        );

                        for (const message of incoming) {
                            merged.set(message.id, message);
                        }

                        return [...merged.values()].sort((a, b) => a.id - b.id);
                    });
                },
            },
        );
    };

    return (
        <>
            <Head title={`Messages · ${conversation.job.title}`} />
            <MessagesShell
                conversations={conversations}
                unreadTotal={unread_total}
                activeId={conversation.id}
                mobilePane="thread"
            >
                <div className="flex h-full min-h-0 flex-col bg-background">
                    <div className="flex shrink-0 items-center gap-3 border-b border-border px-4 py-3 sm:px-6">
                        <Link
                            href="/messages"
                            className="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground lg:hidden"
                            aria-label="Back to conversations"
                        >
                            <ArrowLeft className="h-5 w-5" aria-hidden="true" />
                        </Link>
                        <Briefcase
                            className="hidden h-5 w-5 shrink-0 text-muted-foreground sm:block"
                            aria-hidden="true"
                        />
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-base font-semibold sm:text-lg">
                                    {conversation.job.title}
                                </h1>
                                <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                    {conversation.application_status_label}
                                </span>
                            </div>
                            <p className="truncate text-xs text-muted-foreground">
                                {conversation.company && (
                                    <>
                                        <Building2
                                            className="mr-1 inline h-3 w-3 align-[-1px]"
                                            aria-hidden="true"
                                        />
                                        {conversation.company.name} ·{' '}
                                    </>
                                )}
                                {conversation.viewer_role === 'candidate'
                                    ? `Employer · ${conversation.other_party_name}`
                                    : `Candidate · ${conversation.other_party_name}`}
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="shrink-0"
                        >
                            <Link href={applicationHref}>View application</Link>
                        </Button>
                    </div>

                    <div
                        ref={listRef}
                        role="log"
                        aria-live="polite"
                        aria-label="Conversation messages"
                        className="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                    >
                        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-4 sm:px-6">
                            {messages.next_page_url && (
                                <div className="text-center">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={loadEarlier}
                                    >
                                        Load earlier messages
                                    </Button>
                                </div>
                            )}

                            {items.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No messages yet — say hello.
                                </p>
                            ) : (
                                items.map((message) => {
                                    const isMine = message.is_mine;
                                    const isTombstone =
                                        message.deleted_at !== null;
                                    const isEditing = editingId === message.id;

                                    return (
                                        <div
                                            key={message.id}
                                            className={cn(
                                                'group flex flex-col gap-1',
                                                isMine
                                                    ? 'items-end'
                                                    : 'items-start',
                                            )}
                                        >
                                            <div className="flex max-w-[85%] items-center gap-2 text-xs text-muted-foreground">
                                                {!isMine && (
                                                    <span className="font-medium">
                                                        {message.sender_name}
                                                    </span>
                                                )}
                                                {message.created_at && (
                                                    <time
                                                        dateTime={
                                                            message.created_at
                                                        }
                                                    >
                                                        {new Date(
                                                            message.created_at,
                                                        ).toLocaleString()}
                                                    </time>
                                                )}
                                                {message.edited_at &&
                                                    !isTombstone && (
                                                        <span>(edited)</span>
                                                    )}
                                            </div>

                                            {isTombstone ? (
                                                <div className="max-w-[85%] rounded-2xl border border-dashed border-border px-4 py-2 text-sm text-muted-foreground italic">
                                                    This message was deleted
                                                </div>
                                            ) : isEditing ? (
                                                <form
                                                    onSubmit={(event) =>
                                                        saveEdit(
                                                            event,
                                                            message.id,
                                                        )
                                                    }
                                                    className="flex w-full max-w-[85%] flex-col gap-2"
                                                >
                                                    <Textarea
                                                        value={
                                                            editForm.data.body
                                                        }
                                                        onChange={(event) =>
                                                            editForm.setData(
                                                                'body',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        maxLength={MAX_BODY}
                                                        autoFocus
                                                        rows={2}
                                                    />
                                                    {editForm.errors.body && (
                                                        <InputError
                                                            message={
                                                                editForm.errors
                                                                    .body
                                                            }
                                                        />
                                                    )}
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={cancelEdit}
                                                        >
                                                            <X className="mr-1 h-4 w-4" />
                                                            Cancel
                                                        </Button>
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            disabled={
                                                                editForm.processing
                                                            }
                                                        >
                                                            <Check className="mr-1 h-4 w-4" />
                                                            Save
                                                        </Button>
                                                    </div>
                                                </form>
                                            ) : (
                                                <div className="relative">
                                                    <div
                                                        className={cn(
                                                            'max-w-[85%] rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap',
                                                            isMine
                                                                ? 'rounded-br-md bg-primary text-primary-foreground'
                                                                : 'rounded-bl-md bg-muted text-foreground',
                                                        )}
                                                    >
                                                        {message.body}
                                                    </div>
                                                    {isMine &&
                                                        conversation.can_send && (
                                                            <div className="absolute top-0 -left-2 flex -translate-x-full gap-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        startEdit(
                                                                            message,
                                                                        )
                                                                    }
                                                                    className="rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                                                    aria-label="Edit message"
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setDeleteMessageId(
                                                                            message.id,
                                                                        )
                                                                    }
                                                                    className="rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                                                    aria-label="Delete message"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </button>
                                                            </div>
                                                        )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    <div className="shrink-0 border-t border-border bg-background">
                        <div className="mx-auto w-full max-w-3xl px-4 py-3 sm:px-6">
                            {conversation.can_send ? (
                                <form
                                    onSubmit={submitSend}
                                    className="space-y-2"
                                >
                                    <Textarea
                                        value={sendForm.data.body}
                                        onChange={(event) =>
                                            sendForm.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        onKeyDown={handleComposerKeyDown}
                                        maxLength={MAX_BODY}
                                        rows={2}
                                        placeholder="Write a message…"
                                        aria-label="Message"
                                    />
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-xs text-muted-foreground">
                                            {sendForm.data.body.length}/
                                            {MAX_BODY}
                                        </span>
                                        <Button
                                            type="submit"
                                            disabled={sendForm.processing}
                                        >
                                            Send
                                        </Button>
                                    </div>
                                    {sendForm.errors.body && (
                                        <InputError
                                            message={sendForm.errors.body}
                                        />
                                    )}
                                </form>
                            ) : (
                                <div className="rounded-lg border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
                                    {conversation.read_only_notice ??
                                        'This conversation is read-only.'}
                                </div>
                            )}
                        </div>
                    </div>

                    <Dialog
                        open={deleteMessageId !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setDeleteMessageId(null);
                            }
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Delete message?</DialogTitle>
                                <DialogDescription>
                                    The message body is destroyed permanently
                                    and other participants will see "This
                                    message was deleted". This cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setDeleteMessageId(null)}
                                >
                                    Keep message
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={deleteForm.processing}
                                    onClick={confirmDelete}
                                >
                                    Delete
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </MessagesShell>
        </>
    );
}
