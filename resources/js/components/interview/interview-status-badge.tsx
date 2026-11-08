import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { InterviewStatus } from '@/types';

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

const STATUS_LABELS: Record<InterviewStatus, string> = {
    scheduled: 'Scheduled',
    accepted: 'Accepted',
    declined: 'Declined',
    reschedule_requested: 'New time requested',
    cancelled: 'Cancelled',
};

const STATUS_VARIANTS: Record<InterviewStatus, BadgeVariant> = {
    scheduled: 'secondary',
    accepted: 'default',
    declined: 'destructive',
    reschedule_requested: 'outline',
    cancelled: 'outline',
};

const STATUS_CLASSES: Record<InterviewStatus, string> = {
    scheduled: '',
    accepted: '',
    declined: '',
    reschedule_requested:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200',
    cancelled: 'text-muted-foreground',
};

type Props = {
    status: InterviewStatus;
    label?: string;
};

export function InterviewStatusBadge({ status, label }: Props) {
    return (
        <Badge
            variant={STATUS_VARIANTS[status]}
            className={cn('text-xs', STATUS_CLASSES[status])}
        >
            {label ?? STATUS_LABELS[status]}
        </Badge>
    );
}
