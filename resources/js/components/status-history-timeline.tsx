import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock } from 'lucide-react';

export interface StatusHistory {
    id: number;
    from_status: string;
    from_status_label: string;
    to_status: string;
    to_status_label: string;
    reason: string | null;
    actor_name?: string;
    created_at: string;
}

interface Props {
    histories: StatusHistory[];
}

export function StatusHistoryTimeline({ histories }: Props) {
    if (histories.length === 0) return null;

    return (
        <Card className="border-border">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Clock className="size-5 text-teal-600 dark:text-teal-400" />
                    Status History
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {histories.map((history) => (
                        <div
                            key={history.id}
                            className="relative pb-2 pl-6 last:pb-0"
                        >
                            <div className="absolute top-1.5 left-0 size-2 rounded-full bg-border" />
                            <div className="flex flex-col gap-0.5">
                                <p className="text-sm font-medium text-foreground">
                                    {history.from_status_label} →{' '}
                                    {history.to_status_label}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {new Date(
                                        history.created_at,
                                    ).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric',
                                        hour: 'numeric',
                                        minute: 'numeric',
                                    })}
                                    {history.actor_name &&
                                        ` by ${history.actor_name}`}
                                </p>
                                {history.reason && (
                                    <p className="mt-1 text-sm text-foreground/80 italic">
                                        "{history.reason}"
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
