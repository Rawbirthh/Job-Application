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

export type NotificationsIndexProps = {
    notifications:
        | NotificationItem[]
        | {
              data: NotificationItem[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              prev_page_url: string | null;
              next_page_url: string | null;
          };
    pagination?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
