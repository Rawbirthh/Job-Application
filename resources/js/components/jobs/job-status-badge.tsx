import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATUS_STYLES: Record<string, string> = {
    pending_review:
        'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    published:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    rejected: 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-400',
    closed: 'border-border bg-muted text-muted-foreground',
    draft: 'border-border bg-secondary text-secondary-foreground',
};

const STATUS_LABELS: Record<string, string> = {
    pending_review: 'Pending Review',
    published: 'Published',
    rejected: 'Rejected',
    closed: 'Closed',
    draft: 'Draft',
};

export function formatJobStatus(status: string): string {
    return STATUS_LABELS[status] ?? status.replaceAll('_', ' ');
}

export default function JobStatusBadge({ status }: { status: string }) {
    const style =
        STATUS_STYLES[status] ?? 'border-border bg-muted text-muted-foreground';

    return (
        <Badge variant="outline" className={cn('capitalize', style)}>
            {formatJobStatus(status)}
        </Badge>
    );
}
