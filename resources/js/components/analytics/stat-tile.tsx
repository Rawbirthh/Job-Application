import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type StatTileProps = {
    /** The metric name, e.g. "Total jobs". */
    label: string;
    /** The formatted value, e.g. `42` or `42.9%`. */
    value: number | string;
    /** An optional clarification shown below the value. */
    hint?: string;
    /** Replaces the value with a skeleton while a navigation is in flight. */
    isLoading?: boolean;
};

/**
 * One headline metric of the analytics pages.
 *
 * The label is always rendered so the reader knows what the number means;
 * only the value is replaced by a skeleton while a filter navigation is in
 * flight.
 */
export default function StatTile({
    label,
    value,
    hint,
    isLoading = false,
}: StatTileProps) {
    return (
        <Card className="border-border">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-8 w-24" />
                ) : (
                    <p className="text-2xl font-bold tracking-tight text-foreground">
                        {value}
                    </p>
                )}

                {hint ? (
                    <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
                ) : null}
            </CardContent>
        </Card>
    );
}
