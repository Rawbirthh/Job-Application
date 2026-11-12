import { BarChart3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export type AnalyticsEmptyProps = {
    title: string;
    description: string;
    /** The label of the optional recovery action. */
    actionLabel?: string | null;
    /** The handler of the optional recovery action. */
    onAction?: (() => void) | null;
    /** Disables the action while a navigation is in flight. */
    isLoading?: boolean;
};

/**
 * The empty state of an analytics surface.
 *
 * The copy distinguishes "there is no data yet" from "nothing happened
 * inside the selected window", so a reader never mistakes an empty window
 * for an empty platform.
 */
export default function AnalyticsEmpty({
    title,
    description,
    actionLabel = null,
    onAction = null,
    isLoading = false,
}: AnalyticsEmptyProps) {
    return (
        <Card className="border-border">
            <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <BarChart3
                    aria-hidden="true"
                    className="size-10 text-muted-foreground"
                />
                <div className="space-y-1">
                    <p className="text-sm font-medium text-foreground">
                        {title}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {actionLabel && onAction ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onAction}
                        disabled={isLoading}
                    >
                        {actionLabel}
                    </Button>
                ) : null}
            </CardContent>
        </Card>
    );
}
