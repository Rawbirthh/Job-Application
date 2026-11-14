import type { PaginatedData } from './authorization';

export type ConversationCompany = {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
};

export type ConversationJob = {
    id: number;
    title: string;
};

export type ConversationListItem = {
    id: number;
    viewer_role: 'candidate' | 'employer';
    application_id: number;
    application_status: string;
    job: ConversationJob;
    company: ConversationCompany;
    other_party_name: string;
    unread: boolean;
    last_message: {
        body: string | null;
        sender_id: number;
        sender_name: string | null;
        created_at: string | null;
    } | null;
    last_message_at: string | null;
    created_at: string | null;
};

export type ConversationsPaginated = PaginatedData<ConversationListItem>;

export type MessagesIndexProps = {
    conversations: ConversationsPaginated;
    unread_total: number;
};

export type ConversationMessage = {
    id: number;
    conversation_id: number;
    sender_id: number;
    sender_name: string;
    body: string | null;
    edited_at: string | null;
    deleted_at: string | null;
    created_at: string | null;
    is_mine: boolean;
};

export type MessagesPaginated = PaginatedData<ConversationMessage>;

export type ConversationThread = {
    id: number;
    viewer_role: 'candidate' | 'employer';
    application_id: number;
    application_status: string;
    application_status_label: string;
    job: ConversationJob;
    company: ConversationCompany | null;
    other_party_name: string;
    can_send: boolean;
    read_only_notice: string | null;
};

export type MessagesShowProps = {
    conversation: ConversationThread;
    messages: MessagesPaginated;
    conversations: ConversationsPaginated;
    unread_total: number;
};
