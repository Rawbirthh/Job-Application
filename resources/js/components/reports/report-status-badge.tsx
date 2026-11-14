import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { reportStatusLabel, type ReportStatusValue } from '@/types/report';

const STATUS_STYLES: Record<ReportStatusValue, string> = {
    pending:
        'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    investigating:
        'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    resolved:
        'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    dismissed: 'border-border bg-muted text-muted-foreground',
};

export default function ReportStatusBadge({
    status,
}: {
    status: ReportStatusValue;
}) {
    return (
        <Badge variant="outline" className={cn(STATUS_STYLES[status])}>
            {reportStatusLabel(status)}
        </Badge>
    );
}
