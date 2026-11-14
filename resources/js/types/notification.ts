export type NotificationItem = {
    id: string;
    type: string;
    data: {
        event?: string;
        title?: string;
        message?: string;
        url?: string;
    };
    read_at: string | null;
    created_at: string | null;
};
