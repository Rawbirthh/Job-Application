import { Skeleton } from '@/components/ui/skeleton';

/**
 * One row of a status breakdown.
 *
 * The server always sends the complete status vocabulary, so a zero count
 * is a real value and the row must never be dropped.
 */
export type BreakdownItem = {
    status: string;
    label: string;
    count: number;
};

export type BreakdownBarProps = {
    items: BreakdownItem[];
    /** Replaces the bars with skeletons while a navigation is in flight. */
    isLoading?: boolean;
    /** Shown when the server sent no status at all. */
    emptyMessage?: string;
};

/**
 * A labelled, proportional bar per status.
 *
 * The bar of the largest status fills the track; every other bar is scaled
 * against it. Statuses without a single matching row keep an empty track so
 * the reader can see that the status exists and currently holds nothing.
 */
export default function BreakdownBar({
    items,
    isLoading = false,
    emptyMessage = 'There are no statuses to report on.',
}: BreakdownBarProps) {
    if (isLoading) {
        return (
            <div className="space-y-4" aria-hidden="true">
                {[0, 1, 2].map((index) => (
                    <div key={index} className="space-y-2">
                        <Skeleton className="h-4 w-32" />
                        <Skeleton className="h-2 w-full" />
                    </div>
                ))}
            </div>
        );
    }

    if (items.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </p>
        );
    }

    const maximum = items.reduce(
        (highest, item) => Math.max(highest, item.count),
        0,
    );

    return (
        <ul className="space-y-4">
            {items.map((item) => {
                const percentage =
                    maximum > 0 ? (item.count / maximum) * 100 : 0;

                return (
                    <li key={item.status} className="space-y-1.5">
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <span className="text-foreground">
                                {item.label}
                            </span>
                            <span className="font-medium text-foreground tabular-nums">
                                {item.count}
                            </span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label={`${item.label}: ${item.count}`}
                            aria-valuemin={0}
                            aria-valuemax={Math.max(maximum, 1)}
                            aria-valuenow={item.count}
                            className="h-2 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${Math.round(percentage * 100) / 100}%`,
                                }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
